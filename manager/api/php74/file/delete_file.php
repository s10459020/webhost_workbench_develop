<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
$path = req('path');
if ($path === null) tres('need POST{path}!');
$file = resolve_path($path);
if (!is_file($file)) tres('skip [' . $path . ']');
if (!@unlink($file)) {
    clearstatcache(true, $file);
    if (!is_file($file)) tres('skip [' . $path . ']');
    tres('delete file fail: ' . $file, 500);
}
tres('delete [' . rel_from_root($file) . ']');
