<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    tres('method not allowed', 405);
}

$toBytes = function (string $v): int {
    $s = trim($v);
    if ($s === '') return 0;
    $unit = strtolower(substr($s, -1));
    $num = (float)$s;
    if ($unit === 'g') return (int)($num * 1024 * 1024 * 1024);
    if ($unit === 'm') return (int)($num * 1024 * 1024);
    if ($unit === 'k') return (int)($num * 1024);
    return (int)$num;
};

$uploadMax = (string)ini_get('upload_max_filesize');
$postMax = (string)ini_get('post_max_size');
$workersRaw = getenv('PHP_CLI_SERVER_WORKERS');
$workers = ($workersRaw === false || trim((string)$workersRaw) === '') ? '1' : trim((string)$workersRaw);

jres([
    'php' => [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'os_family' => PHP_OS_FAMILY,
        'os' => PHP_OS,
        'binary' => PHP_BINARY,
    ],
    'server' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
        'name' => $_SERVER['SERVER_NAME'] ?? '',
        'addr' => $_SERVER['SERVER_ADDR'] ?? '',
        'port' => $_SERVER['SERVER_PORT'] ?? '',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        'workers' => $workers,
    ],
    'limits' => [
        'upload_max_filesize' => $uploadMax,
        'upload_max_filesize_bytes' => $toBytes($uploadMax),
        'post_max_size' => $postMax,
        'post_max_size_bytes' => $toBytes($postMax),
        'max_file_uploads' => (string)ini_get('max_file_uploads'),
        'memory_limit' => (string)ini_get('memory_limit'),
        'max_execution_time' => (string)ini_get('max_execution_time'),
        'max_input_time' => (string)ini_get('max_input_time'),
    ],
    'workspace_root' => root_dir(),
    'loaded_extensions_count' => count(get_loaded_extensions()),
    'loaded_extensions' => get_loaded_extensions(),
]);
