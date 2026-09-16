<?php
declare(strict_types=1);
require_once __DIR__ . '/../../php74/api_lib/_lib.php';

function ftp_req_config(): array {
    $host = trim((string)req('host', ''));
    $user = (string)req('user', '');
    $password = (string)req('password', '');
    $path = trim((string)req('path', '/'));
    $mode = strtolower(trim((string)req('mode', 'ftp')));
    if ($host === '') tres('host is required', 400);
    if ($user === '') tres('user is required', 400);
    if ($path === '') $path = '/';
    if (!in_array($mode, ['ftp', 'ftps_explicit', 'ftps_implicit', 'sftp'], true)) {
        tres('unsupported mode: ' . $mode, 400);
    }
    $port = 21;
    if ($mode === 'ftps_implicit') $port = 990;
    if ($mode === 'sftp') $port = 22;
    return [
        'host' => $host,
        'port' => $port,
        'user' => $user,
        'password' => $password,
        'path' => $path,
        'mode' => $mode,
    ];
}

function ftp_sock_write($sock, string $line): void {
    $ok = @fwrite($sock, $line . "\r\n");
    if ($ok === false) tres('ftp write failed', 500);
}

function ftp_sock_read_response($sock): array {
    $lines = [];
    $code = 0;
    $first = '';
    while (!feof($sock)) {
        $line = fgets($sock, 8192);
        if ($line === false) break;
        $line = rtrim($line, "\r\n");
        $lines[] = $line;
        if (preg_match('/^(\d{3})([\s-])(.*)$/', $line, $m) === 1) {
            if ($first === '') {
                $code = (int)$m[1];
                $first = (string)$m[1];
                if ($m[2] === ' ') break;
                continue;
            }
            if ((string)$m[1] === $first && $m[2] === ' ') break;
        }
    }
    if ($code === 0) tres('ftp invalid response: ' . implode("\n", $lines), 500);
    return ['code' => $code, 'text' => implode("\n", $lines)];
}

function ftp_sock_expect($sock, array $okCodes, string $errorLabel): array {
    $res = ftp_sock_read_response($sock);
    if (!in_array((int)$res['code'], $okCodes, true)) {
        tres($errorLabel . ': ' . $res['text'], 500);
    }
    return $res;
}

function ftp_sock_cmd($sock, string $cmd, array $okCodes, string $errorLabel): array {
    ftp_sock_write($sock, $cmd);
    return ftp_sock_expect($sock, $okCodes, $errorLabel);
}
function ftp_sock_try_cmd($sock, string $cmd): array {
    ftp_sock_write($sock, $cmd);
    return ftp_sock_read_response($sock);
}

function ftp_crypto_enable($sock): void {
    @stream_context_set_option($sock, 'ssl', 'verify_peer', false);
    @stream_context_set_option($sock, 'ssl', 'verify_peer_name', false);
    @stream_context_set_option($sock, 'ssl', 'allow_self_signed', true);
    $ok = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if ($ok !== true) tres('ftp tls enable failed', 500);
}

function ftp_open(array $cfg): array {
    if (ftp_use_winscp_bridge($cfg)) {
        return ['mode' => $cfg['mode'], 'cfg' => $cfg, 'winscp' => true];
    }
    if ($cfg['mode'] === 'sftp') {
        return ['mode' => 'sftp', 'cfg' => $cfg];
    }

    $errno = 0;
    $errstr = '';
    $sock = @stream_socket_client(
        'tcp://' . $cfg['host'] . ':' . (int)$cfg['port'],
        $errno,
        $errstr,
        15
    );
    if ($sock === false) tres('ftp connect failed: ' . $errstr, 500);
    @stream_set_timeout($sock, 30);

    ftp_sock_expect($sock, [220], 'ftp welcome failed');

    if ($cfg['mode'] === 'ftp') {
        ftp_sock_cmd($sock, 'USER ' . $cfg['user'], [230, 331], 'ftp USER failed');
        $passRes = ftp_sock_cmd($sock, 'PASS ' . $cfg['password'], [230, 202], 'ftp PASS failed');
        if ((int)$passRes['code'] !== 230 && (int)$passRes['code'] !== 202) {
            tres('ftp login failed', 500);
        }
    } else if ($cfg['mode'] === 'ftps_implicit') {
        ftp_crypto_enable($sock);
        ftp_sock_cmd($sock, 'USER ' . $cfg['user'], [230, 331], 'ftp USER(TLS) failed');
        $passRes2 = ftp_sock_cmd($sock, 'PASS ' . $cfg['password'], [230, 202], 'ftp PASS(TLS) failed');
        if ((int)$passRes2['code'] !== 230 && (int)$passRes2['code'] !== 202) {
            tres('ftp login(TLS) failed', 500);
        }
    } else if ($cfg['mode'] === 'ftps_explicit') {
        ftp_sock_cmd($sock, 'AUTH TLS', [234, 220], 'ftp AUTH TLS failed');
        ftp_crypto_enable($sock);
        ftp_sock_cmd($sock, 'USER ' . $cfg['user'], [230, 331], 'ftp USER(TLS) failed');
        $passRes2 = ftp_sock_cmd($sock, 'PASS ' . $cfg['password'], [230, 202], 'ftp PASS(TLS) failed');
        if ((int)$passRes2['code'] !== 230 && (int)$passRes2['code'] !== 202) {
            tres('ftp login(TLS) failed', 500);
        }
    }

    ftp_sock_cmd($sock, 'TYPE I', [200], 'ftp TYPE failed');
    $dataTls = false;
    if ($cfg['mode'] === 'ftps_explicit' || $cfg['mode'] === 'ftps_implicit') {
        ftp_sock_cmd($sock, 'PBSZ 0', [200], 'ftp PBSZ failed');
        $prot = ftp_sock_try_cmd($sock, 'PROT C');
        if ((int)$prot['code'] === 200) {
            $dataTls = false;
        } else {
            $protP = ftp_sock_cmd($sock, 'PROT P', [200], 'ftp PROT failed');
            if ((int)$protP['code'] === 200) $dataTls = true;
        }
    }

    return ['sock' => $sock, 'mode' => $cfg['mode'], 'data_tls' => $dataTls];
}

function ftp_close_safe($conn): void {
    if (($conn['mode'] ?? '') === 'sftp') return;
    $sock = $conn['sock'] ?? null;
    if (!is_resource($sock)) return;
    @fwrite($sock, "QUIT\r\n");
    @fclose($sock);
}

function sftp_path(string $path): string {
    $p = str_replace('\\', '/', trim($path));
    if ($p === '') return '/';
    return $p[0] === '/' ? $p : '/' . $p;
}

function sftp_tool_path(): string {
    return dirname(__DIR__, 3) . '/tools/winscp/WinSCP.com';
}
function sshpass_tool_path(): string {
    return dirname(__DIR__, 3) . '/tools/sshpass/sshpass';
}
function ftp_use_winscp_bridge(array $cfg): bool {
    if (DIRECTORY_SEPARATOR !== '\\') return false;
    return in_array(($cfg['mode'] ?? ''), ['ftps_explicit', 'ftps_implicit'], true);
}

function sftp_quote(string $v): string {
    return '"' . str_replace('"', '""', $v) . '"';
}

function sftp_session_url(array $cfg): string {
    $u = rawurlencode((string)$cfg['user']);
    $p = rawurlencode((string)$cfg['password']);
    $h = (string)$cfg['host'];
    $port = (int)$cfg['port'];
    return "sftp://{$u}:{$p}@{$h}:{$port}/";
}
function winscp_session_url(array $cfg): string {
    $u = rawurlencode((string)$cfg['user']);
    $p = rawurlencode((string)$cfg['password']);
    $h = (string)$cfg['host'];
    $port = (int)$cfg['port'];
    $mode = (string)$cfg['mode'];
    if ($mode === 'ftps_implicit') return "ftps://{$u}:{$p}@{$h}:{$port}/";
    if ($mode === 'ftps_explicit') return "ftp://{$u}:{$p}@{$h}:{$port}/";
    if ($mode === 'ftp') return "ftp://{$u}:{$p}@{$h}:{$port}/";
    return sftp_session_url($cfg);
}
function winscp_open_line(array $cfg): string {
    $mode = (string)$cfg['mode'];
    $line = 'open ' . sftp_quote(winscp_session_url($cfg));
    if ($mode === 'ftps_explicit') $line .= ' -explicit -certificate=*';
    else if ($mode === 'ftps_implicit') $line .= ' -implicit -certificate=*';
    else if ($mode === 'sftp') $line .= ' -hostkey=*';
    return $line;
}
function winscp_run(array $cfg, array $commands): array {
    $tool = sftp_tool_path();
    if (!is_file($tool)) tres('winscp tool not found: ' . $tool, 500);
    $script = [];
    $script[] = 'option batch abort';
    $script[] = 'option confirm off';
    $script[] = winscp_open_line($cfg);
    foreach ($commands as $c) $script[] = $c;
    $script[] = 'exit';

    $tmp = tempnam(sys_get_temp_dir(), 'winscp_');
    if ($tmp === false) tres('temp create fail', 500);
    file_put_contents($tmp, implode("\r\n", $script) . "\r\n");
    $out = [];
    $code = 0;
    $cmd = escapeshellarg($tool) . ' /ini=nul /script=' . escapeshellarg($tmp) . ' 2>&1';
    @exec($cmd, $out, $code);
    @unlink($tmp);
    if ($code !== 0) tres("winscp command failed:\n" . implode("\n", $out), 500);
    return $out;
}

function sftp_run(array $cfg, array $commands): array {
    if (DIRECTORY_SEPARATOR === '\\') {
        return winscp_run($cfg, $commands);
    }

    $sftpBin = '/usr/bin/sftp';
    if (!is_executable($sftpBin)) tres('sftp binary not found: ' . $sftpBin, 500);
    $sshpass = sshpass_tool_path();
    $useSshPass = ($cfg['password'] ?? '') !== '' && is_executable($sshpass);
    if (($cfg['password'] ?? '') !== '' && !$useSshPass) {
        tres('sshpass tool is required at manager/tools/sshpass/sshpass for password-based sftp', 500);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'sftpb_');
    if ($tmp === false) tres('temp create fail', 500);
    file_put_contents($tmp, implode("\n", $commands) . "\n");

    $host = (string)$cfg['host'];
    $port = (int)$cfg['port'];
    $user = (string)$cfg['user'];
    $login = escapeshellarg($user . '@' . $host);
    $home = rtrim(sys_get_temp_dir(), '/\\') . '/shv_ssh_home';
    if (!is_dir($home)) @mkdir($home, 0777, true);
    $base = $sftpBin
        . ' -oBatchMode=no'
        . ' -oPreferredAuthentications=password'
        . ' -oPubkeyAuthentication=no'
        . ' -oStrictHostKeyChecking=no'
        . ' -oUserKnownHostsFile=/dev/null'
        . ' -oGlobalKnownHostsFile=/dev/null'
        . ' -P ' . $port
        . ' -b ' . escapeshellarg($tmp)
        . ' ' . $login
        . ' 2>&1';
    $shellCmd = 'HOME=' . escapeshellarg($home) . ' ' . $base;
    $cmd = $useSshPass
        ? (escapeshellarg($sshpass) . ' -p ' . escapeshellarg((string)$cfg['password']) . ' sh -lc ' . escapeshellarg($shellCmd))
        : ('sh -lc ' . escapeshellarg($shellCmd));

    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);
    @unlink($tmp);
    if ($code !== 0) tres("linux sftp command failed:\n" . implode("\n", $out), 500);
    return $out;
}

function sftp_list_rows(array $cfg, string $path): array {
    $remote = sftp_path($path);
    $out = sftp_run($cfg, [
        'cd ' . sftp_quote($remote),
        'ls -l'
    ]);
    $rows = [];
    foreach ($out as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, 'Session') !== false || strpos($line, 'winscp>') === 0) continue;
        if (preg_match('/^([\-dl])[rwxstST-]{9}\s+\d+\s+\S+\s+\S+\s+(\d+)\s+\S+\s+\d+\s+\S+\s+(.+)$/', $line, $m) !== 1) continue;
        $type = ($m[1] === 'd') ? 'dir' : 'file';
        $name = trim($m[3]);
        if ($name === '.' || $name === '..') continue;
        $full = ($remote === '/' ? '/' . $name : rtrim($remote, '/') . '/' . $name);
        $rows[] = ['name' => $name, 'path' => $full, 'type' => $type, 'size' => $type === 'file' ? (int)$m[2] : 0];
    }
    usort($rows, fn($a, $b) => strcmp(($a['type'] === 'dir' ? '0' : '1') . $a['name'], ($b['type'] === 'dir' ? '0' : '1') . $b['name']));
    return $rows;
}
function winscp_list_rows(array $cfg, string $path, ?array &$rawOut = null): array {
    $remote = sftp_path($path);
    $out = winscp_run($cfg, [
        'cd ' . sftp_quote($remote),
        'ls'
    ]);
    $rawOut = $out;
    $rows = [];
    foreach ($out as $line) {
        $line = trim((string)$line);
        if ($line === '' || stripos($line, 'session') !== false || stripos($line, 'winscp>') === 0) continue;
        if (stripos($line, 'total ') === 0) continue;

        // WinSCP CLI listing format:
        // D---------   0                           0 May 14 18:21:12 2026 manager
        // ----------   0                         619 May 12 14:49:25 2026 index.php
        if (preg_match('/^([A-Za-z\-]{10,})\s+(\d+)\s+(\d+)\s+(.+)$/', $line, $m) !== 1) continue;
        $perm = strtoupper($m[1]);
        $type = (substr($perm, 0, 1) === 'D') ? 'dir' : 'file';
        $size = ($type === 'file') ? (int)$m[3] : 0;
        $rest = trim($m[4]);
        $parts = preg_split('/\s+/', $rest);
        if (!is_array($parts) || count($parts) < 1) continue;
        $name = (string)$parts[count($parts) - 1];

        if ($name === '.' || $name === '..') continue;
        $full = ($remote === '/' ? '/' . $name : rtrim($remote, '/') . '/' . $name);
        $rows[] = ['name' => $name, 'path' => $full, 'type' => $type, 'size' => $size];
    }
    usort($rows, fn($a, $b) => strcmp(($a['type'] === 'dir' ? '0' : '1') . $a['name'], ($b['type'] === 'dir' ? '0' : '1') . $b['name']));
    return $rows;
}

function ftp_pasv_open_data(array $conn, string $cmd): array {
    $sock = $conn['sock'];
    $pasv = ftp_sock_cmd($sock, 'PASV', [227], 'ftp PASV failed');
    if (preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', (string)$pasv['text'], $m) !== 1) {
        tres('ftp PASV parse failed: ' . $pasv['text'], 500);
    }
    $host = $m[1] . '.' . $m[2] . '.' . $m[3] . '.' . $m[4];
    $port = ((int)$m[5] * 256) + (int)$m[6];
    $errno = 0;
    $errstr = '';
    $data = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 15);
    if ($data === false) tres('ftp data connect failed: ' . $errstr, 500);
    @stream_set_timeout($data, 30);
    ftp_sock_cmd($sock, $cmd, [150, 125], 'ftp transfer start failed');
    if (($conn['data_tls'] ?? false) === true) {
        ftp_crypto_enable($data);
    }
    return ['data' => $data, 'sock' => $sock];
}

function ftp_finish_transfer(array $transfer): void {
    $data = $transfer['data'];
    $sock = $transfer['sock'];
    if (is_resource($data)) @fclose($data);
    ftp_sock_expect($sock, [226, 250], 'ftp transfer finish failed');
}

function ftp_list_children(array $conn, string $dirPath): array {
    $xfer = ftp_pasv_open_data($conn, 'LIST ' . $dirPath);
    $raw = stream_get_contents($xfer['data']);
    ftp_finish_transfer($xfer);

    $rows = [];
    foreach (preg_split('/\r?\n/', (string)$raw) as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, 'total') === 0) continue;
        $parts = preg_split('/\s+/', $line, 9);
        if (!is_array($parts) || count($parts) < 9) continue;
        $perm = (string)$parts[0];
        $name = (string)$parts[8];
        if ($name === '.' || $name === '..') continue;
        $type = (strlen($perm) > 0 && strtolower($perm[0]) === 'd') ? 'dir' : 'file';
        $full = rtrim($dirPath, '/') . '/' . $name;
        if ($dirPath === '/') $full = '/' . $name;
        $rows[] = ['name' => $name, 'path' => $full, 'type' => $type];
    }
    return $rows;
}

function ftp_delete_file_strict(array $conn, string $path): void {
    ftp_sock_cmd($conn['sock'], 'DELE ' . $path, [250], 'ftp delete file failed');
}

function ftp_delete_dir_recursive_strict(array $conn, string $path): void {
    $p = trim($path);
    if ($p === '') return;
    $children = ftp_list_children($conn, $p);
    foreach ($children as $child) {
        if (($child['type'] ?? '') === 'dir') {
            ftp_delete_dir_recursive_strict($conn, (string)$child['path']);
        } else {
            ftp_delete_file_strict($conn, (string)$child['path']);
        }
    }
    ftp_sock_cmd($conn['sock'], 'RMD ' . $p, [250], 'ftp remove dir failed');
}

function sftp_delete_file_strict(array $cfg, string $path): void {
    sftp_run($cfg, ['rm ' . sftp_quote(sftp_path($path))]);
}

function sftp_delete_dir_recursive_strict(array $cfg, string $path): void {
    $p = sftp_path($path);
    $children = sftp_list_rows($cfg, $p);
    foreach ($children as $child) {
        if (($child['type'] ?? '') === 'dir') {
            sftp_delete_dir_recursive_strict($cfg, (string)$child['path']);
        } else {
            sftp_delete_file_strict($cfg, (string)$child['path']);
        }
    }
    sftp_run($cfg, ['rmdir ' . sftp_quote($p)]);
}

function winscp_delete_file_strict(array $cfg, string $path): void {
    winscp_run($cfg, ['rm ' . sftp_quote(sftp_path($path))]);
}

function winscp_delete_dir_recursive_strict(array $cfg, string $path): void {
    $p = sftp_path($path);
    $children = winscp_list_rows($cfg, $p);
    foreach ($children as $child) {
        if (($child['type'] ?? '') === 'dir') {
            winscp_delete_dir_recursive_strict($cfg, (string)$child['path']);
        } else {
            winscp_delete_file_strict($cfg, (string)$child['path']);
        }
    }
    winscp_run($cfg, ['rmdir ' . sftp_quote($p)]);
}

