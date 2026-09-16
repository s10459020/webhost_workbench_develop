<?php
declare(strict_types=1);
require_once __DIR__ . '/_ftp_lib.php';

$cfg = ftp_req_config();
$filePath = trim((string)req('file_path', ''));
$targetType = strtolower(trim((string)req('target_type', '')));
if ($filePath === '') tres('file_path is required', 400);
if (!in_array($targetType, ['file', 'dir'], true)) tres('target_type must be file or dir', 400);
$conn = ftp_open($cfg);
if (($conn['mode'] ?? '') === 'sftp') {
    if ($targetType === 'dir') {
        sftp_delete_dir_recursive_strict($conn['cfg'], $filePath);
    } else {
        sftp_delete_file_strict($conn['cfg'], $filePath);
    }
} else if (ftp_use_winscp_bridge($cfg)) {
    if ($targetType === 'dir') {
        winscp_delete_dir_recursive_strict($cfg, $filePath);
    } else {
        winscp_delete_file_strict($cfg, $filePath);
    }
} else {
    if ($targetType === 'dir') {
        ftp_delete_dir_recursive_strict($conn, $filePath);
    } else {
        ftp_delete_file_strict($conn, $filePath);
    }
    ftp_close_safe($conn);
}
jres(['ok' => true, 'path' => $filePath, 'target_type' => $targetType]);
