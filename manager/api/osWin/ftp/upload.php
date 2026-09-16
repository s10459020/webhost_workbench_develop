<?php
declare(strict_types=1);
require_once __DIR__ . '/_ftp_lib.php';

if (!isset($_FILES['file'])) tres('need FILE{file}', 400);
$cfg = ftp_req_config();
$targetDir = trim((string)req('target_dir', ''));
if ($targetDir === '') $targetDir = $cfg['path'];
$tmp = (string)$_FILES['file']['tmp_name'];
$name = basename((string)$_FILES['file']['name']);
if ($name === '') tres('file name is empty', 400);
$target = rtrim($targetDir, '/') . '/' . $name;
if ($targetDir === '/') $target = '/' . $name;

$conn = ftp_open($cfg);
if (($conn['mode'] ?? '') === 'sftp') {
    sftp_run($conn['cfg'], [
        'put ' . sftp_quote($tmp) . ' ' . sftp_quote(sftp_path($target))
    ]);
} else if (ftp_use_winscp_bridge($cfg)) {
    winscp_run($cfg, [
        'put ' . sftp_quote($tmp) . ' ' . sftp_quote(sftp_path($target))
    ]);
} else {
    $xfer = ftp_pasv_open_data($conn, 'STOR ' . $target);
    $in = @fopen($tmp, 'rb');
    if ($in === false) tres('upload tmp open fail', 500);
    @stream_copy_to_stream($in, $xfer['data']);
    @fclose($in);
    ftp_finish_transfer($xfer);
    ftp_close_safe($conn);
}
jres(['ok' => true, 'path' => $target]);
