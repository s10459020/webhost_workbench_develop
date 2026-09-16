<?php
declare(strict_types=1);
require_once __DIR__ . '/_ftp_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tres('method not allowed', 405);
$host = trim((string)req('host', ''));
$user = (string)req('user', '');
$pass = (string)req('pass', '');
if ($host === '') tres('host is required', 400);

function probe_connect(string $host, int $port, int $timeout = 6): array {
    $errno = 0; $errstr = '';
    $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
    if ($sock === false) return ['ok' => false, 'error' => trim($errstr) ?: "errno={$errno}"];
    @stream_set_timeout($sock, $timeout);
    return ['ok' => true, 'sock' => $sock];
}
function probe_read_banner($sock): string {
    $line = @fgets($sock, 8192);
    return $line === false ? '' : rtrim((string)$line, "\r\n");
}
function probe_read_reply($sock): string {
    $first = @fgets($sock, 8192);
    if ($first === false) return '';
    $first = rtrim((string)$first, "\r\n");
    if (!preg_match('/^(\d{3})([\s-])/', $first, $m)) return $first;
    $code = $m[1];
    $sep = $m[2];
    if ($sep !== '-') return $first;
    $lines = [$first];
    while (!feof($sock)) {
        $line = @fgets($sock, 8192);
        if ($line === false) break;
        $line = rtrim((string)$line, "\r\n");
        $lines[] = $line;
        if (preg_match('/^' . $code . '\s/', $line)) break;
    }
    return implode("\n", $lines);
}
function probe_cmd($sock, string $cmd): string {
    @fwrite($sock, $cmd . "\r\n");
    return probe_read_reply($sock);
}
function probe_code(string $line): int {
    return preg_match('/^(\d{3})/', $line, $m) ? (int)$m[1] : 0;
}
function probe_tls($sock): array {
    @stream_context_set_option($sock, 'ssl', 'verify_peer', false);
    @stream_context_set_option($sock, 'ssl', 'verify_peer_name', false);
    @stream_context_set_option($sock, 'ssl', 'allow_self_signed', true);
    $ok = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if ($ok === true) return ['ok' => true];
    $err = error_get_last();
    return ['ok' => false, 'error' => (string)($err['message'] ?? 'tls failed')];
}

$result = [
    'host' => $host,
    'ports' => [],
    'auth_tls' => ['supported' => false, 'state' => 'unknown', 'response' => ''],
    'feat' => ['response' => ''],
    'login_plain' => ['tested' => false, 'ok' => false, 'user_response' => '', 'pass_response' => ''],
    'tls_on_21' => ['ok' => false, 'error' => 'not tried'],
    'tls_on_990' => ['ok' => false, 'error' => 'not tried'],
    'login_tls' => ['tested' => false, 'ok' => false, 'user_response' => '', 'pass_response' => '']
];

// Port 21 control
$c21 = probe_connect($host, 21);
if ($c21['ok']) {
    $sock21 = $c21['sock'];
    $banner21 = probe_read_reply($sock21);
    $result['ports']['21'] = ['open' => true, 'banner' => $banner21];
    $feat = probe_cmd($sock21, 'FEAT');
    $result['feat'] = ['response' => $feat];
    if ($user !== '' && $pass !== '') {
        $u0 = probe_cmd($sock21, 'USER ' . $user);
        $p0 = '';
        $u0Code = probe_code($u0);
        if ($u0Code === 331) $p0 = probe_cmd($sock21, 'PASS ' . $pass);
        $plainOk = ($u0Code === 230) || (probe_code($p0) === 230);
        $result['login_plain'] = [
            'tested' => true,
            'ok' => $plainOk,
            'user_response' => $u0,
            'pass_response' => $p0
        ];
    }
    $auth = probe_cmd($sock21, 'AUTH TLS');
    $authCode = probe_code($auth);
    $authState = 'unknown';
    if (in_array($authCode, [234, 220], true)) $authState = 'supported';
    else if (in_array($authCode, [500, 501, 502, 504], true)) $authState = 'unsupported';
    else if ($authCode === 530) $authState = 'need_login';
    else if ($authCode === 503) $authState = 'need_auth_first';
    $result['auth_tls'] = [
        'supported' => ($authState === 'supported'),
        'state' => $authState,
        'response' => $auth
    ];
    if ($result['auth_tls']['supported']) {
        $tls = probe_tls($sock21);
        $result['tls_on_21'] = $tls;
        if ($tls['ok']) {
            if ($user !== '' && $pass !== '') {
                $u = probe_cmd($sock21, 'USER ' . $user);
                $p = '';
                $uCode = probe_code($u);
                if ($uCode === 331) $p = probe_cmd($sock21, 'PASS ' . $pass);
                $tlsOk = ($uCode === 230) || (probe_code($p) === 230);
                $result['login_tls'] = [
                    'tested' => true,
                    'ok' => $tlsOk,
                    'user_response' => $u,
                    'pass_response' => $p
                ];
            }
        }
    }
    @fwrite($sock21, "QUIT\r\n");
    @fclose($sock21);
} else {
    $result['ports']['21'] = ['open' => false, 'error' => $c21['error']];
}

// Port 990 implicit tls probe
$c990 = probe_connect($host, 990);
if ($c990['ok']) {
    $sock990 = $c990['sock'];
    $result['ports']['990'] = ['open' => true];
    $tls990 = probe_tls($sock990);
    $result['tls_on_990'] = $tls990;
    @fwrite($sock990, "QUIT\r\n");
    @fclose($sock990);
} else {
    $result['ports']['990'] = ['open' => false, 'error' => $c990['error']];
}

// Port 22 ssh/sftp probe
$c22 = probe_connect($host, 22);
if ($c22['ok']) {
    $sock22 = $c22['sock'];
    $banner22 = probe_read_banner($sock22);
    $result['ports']['22'] = ['open' => true, 'banner' => $banner22, 'is_ssh' => (strncmp($banner22, 'SSH-', 4) === 0)];
    @fclose($sock22);
} else {
    $result['ports']['22'] = ['open' => false, 'error' => $c22['error']];
}

jres($result);


