<?php
declare(strict_types=1);
require_once __DIR__ . '/_ftp_lib.php';

$cfg = ftp_req_config();
$dirPath = trim((string)req('dir_path', ''));
if ($dirPath === '') tres('dir_path is required', 400);
$conn = ftp_open($cfg);
if (($conn['mode'] ?? '') === 'sftp') {
    sftp_run($conn['cfg'], [
        'mkdir ' . sftp_quote(sftp_path($dirPath))
    ]);
} else if (ftp_use_winscp_bridge($cfg)) {
    winscp_run($cfg, [
        'mkdir ' . sftp_quote(sftp_path($dirPath))
    ]);
} else {
    ftp_sock_cmd($conn['sock'], 'MKD ' . $dirPath, [257], 'ftp mkdir failed');
    ftp_close_safe($conn);
}
jres(['ok' => true, 'path' => $dirPath]);
