<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

if (!isset($_FILES['files'])) tres('need FILE{files}!');
$dirs = $_POST['dirs'];
$mtimes = $_POST['mtimes'] ?? [];
$names = $_FILES['files']['name'];
$tmpNames = $_FILES['files']['tmp_name'];
$errs = $_FILES['files']['error'];
$count = count($names);
$savedFiles = [];
$savedDetails = [];
$errors = [];

for ($i = 0; $i < $count; $i++) {
    $errCode = (int)$errs[$i];
    if ($errCode !== UPLOAD_ERR_OK) {
        $errors[] = [
            'index' => $i,
            'name' => (string)$names[$i],
            'error' => 'upload err code: ' . $errCode,
        ];
        continue;
    }

    $targetDirInput = (string)$dirs[$i];
    $targetDir = resolve_path($targetDirInput);
    if (!create_dir_open($targetDir)) {
        $errors[] = [
            'index' => $i,
            'name' => (string)$names[$i],
            'error' => 'mkdir fail: ' . $targetDir,
            'target_dir' => $targetDir,
            'parent_dir' => dirname($targetDir),
            'parent_exists' => is_dir(dirname($targetDir)),
            'parent_writable' => is_writable(dirname($targetDir)),
        ];
        continue;
    }

    $rawMtime = isset($mtimes[$i]) ? (string)$mtimes[$i] : '';
    $requestedMtime = 0;
    $tmpTouchOk = null;
    if ($rawMtime !== '') {
        $n = (int)$rawMtime;
        if ($n > 0) {
            if ($n > 20000000000) $n = (int)floor($n / 1000);
            $requestedMtime = $n;
            $tmpTouchOk = @touch((string)$tmpNames[$i], $requestedMtime);
        }
    }

    $baseName = basename((string)$names[$i]);
    $targetFile = path_join($targetDir, $baseName);
    $ok = move_uploaded_file_open((string)$tmpNames[$i], $targetFile, $requestedMtime);
    if (!$ok) {
        $errors[] = [
            'index' => $i,
            'name' => $baseName,
            'error' => 'move fail: ' . $targetFile,
            'target_dir' => $targetDir,
            'target_file' => $targetFile,
            'tmp_name' => (string)$tmpNames[$i],
            'tmp_exists' => is_file((string)$tmpNames[$i]),
            'dir_exists' => is_dir($targetDir),
            'dir_writable' => is_writable($targetDir),
            'target_exists' => is_file($targetFile),
            'target_writable' => is_writable($targetFile),
        ];
        continue;
    }

    $touchOk = null;
    if ($requestedMtime > 0) $touchOk = @touch($targetFile, $requestedMtime);
    clearstatcache(true, $targetFile);

    $savedFiles[] = rel_from_root($targetFile);
    $savedDetails[] = [
        'path' => rel_from_root($targetFile),
        'requested_mtime' => $requestedMtime,
        'actual_mtime' => (int)filemtime($targetFile),
        'tmp_touch_ok' => $tmpTouchOk,
        'touch_ok' => $touchOk,
    ];
}

jres([
    'count' => $count,
    'files' => $savedFiles,
    'details' => $savedDetails,
    'saved' => count($savedFiles),
    'errors' => $errors,
]);
