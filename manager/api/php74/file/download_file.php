<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
require_once __DIR__ . '/../api_lib/download_lib.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tres('method not allowed', 405);
$path = req('path');
if ($path === null || $path === '') tres('need POST{path}!');
$file = resolve_path($path);
if (!is_file($file)) tres($path . ' is not a file', 404);
header('Content-Type: application/octet-stream');
download_header_filename(basename($file));
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
