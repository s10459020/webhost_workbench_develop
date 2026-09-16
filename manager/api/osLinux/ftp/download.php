<?php
declare(strict_types=1);
require_once __DIR__ . '/_ftp_lib.php';

$cfg = ftp_req_config();
$filePath = trim((string)req('file_path', ''));
if ($filePath === '') tres('file_path is required', 400);
$conn = ftp_open($cfg);
$tmp = tempnam(sys_get_temp_dir(), 'ftpdl_');
if ($tmp === false) tres('temp create fail', 500);

if (($conn['mode'] ?? '') === 'sftp') {
    sftp_run($conn['cfg'], [
        'get ' . sftp_quote(sftp_path($filePath)) . ' ' . sftp_quote($tmp)
    ]);
} else if (ftp_use_winscp_bridge($cfg)) {
    winscp_run($cfg, [
        'get ' . sftp_quote(sftp_path($filePath)) . ' ' . sftp_quote($tmp)
    ]);
} else {
    $xfer = ftp_pasv_open_data($conn, 'RETR ' . $filePath);
    $out = @fopen($tmp, 'wb');
    if ($out === false) tres('temp open fail', 500);
    @stream_copy_to_stream($xfer['data'], $out);
    @fclose($out);
    ftp_finish_transfer($xfer);
    ftp_close_safe($conn);
}

if (!is_file($tmp)) tres('ftp download failed: ' . $filePath, 500);
header('Content-Type: application/octet-stream');
$base = basename(str_replace('\\', '/', $filePath));
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($base));
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
