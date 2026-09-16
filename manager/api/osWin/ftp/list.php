<?php
declare(strict_types=1);
require_once __DIR__ . '/_ftp_lib.php';

$cfg = ftp_req_config();
$conn = ftp_open($cfg);
$path = $cfg['path'];
$debugRaw = ((string)req('debug_raw', '0') === '1');

if (($conn['mode'] ?? '') === 'sftp') {
    $rows = sftp_list_rows($conn['cfg'], $path);
    jres(['path' => $path, 'rows' => $rows]);
}
if (ftp_use_winscp_bridge($cfg)) {
    $raw = [];
    $rows = winscp_list_rows($cfg, $path, $raw);
    if ($debugRaw || count($rows) === 0) jres(['path' => $path, 'rows' => $rows, 'raw' => $raw]);
    jres(['path' => $path, 'rows' => $rows]);
}

$xfer = ftp_pasv_open_data($conn, 'LIST ' . $path);
$raw = stream_get_contents($xfer['data']);
ftp_finish_transfer($xfer);
ftp_close_safe($conn);

$rows = [];
foreach (preg_split('/\r?\n/', (string)$raw) as $line) {
    $line = trim((string)$line);
    if ($line === '' || strpos($line, 'total') === 0) continue;
    $parts = preg_split('/\s+/', $line, 9);
    if (!is_array($parts) || count($parts) < 9) continue;
    $perm = (string)$parts[0];
    $size = (int)$parts[4];
    $name = (string)$parts[8];
    if ($name === '.' || $name === '..') continue;
    $type = (strlen($perm) > 0 && $perm[0] === 'd') ? 'dir' : 'file';
    $full = rtrim($path, '/') . '/' . $name;
    if ($path === '/') $full = '/' . $name;
    $rows[] = ['name' => $name, 'path' => $full, 'type' => $type, 'size' => $type === 'file' ? $size : 0];
}
usort($rows, fn($a, $b) => strcmp(($a['type'] === 'dir' ? '0' : '1') . $a['name'], ($b['type'] === 'dir' ? '0' : '1') . $b['name']));
jres(['path' => $path, 'rows' => $rows]);
