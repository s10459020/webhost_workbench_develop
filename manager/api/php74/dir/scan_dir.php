<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

function dir_size_bytes(string $dir): int {
    $size = 0;
    $items = scandir($dir);
    if ($items === false) return 0;
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;
        $full = $dir . '/' . $name;
        if (is_dir($full)) {
            $size += dir_size_bytes($full);
        } elseif (is_file($full)) {
            $fileSize = filesize($full);
            if ($fileSize !== false) $size += $fileSize;
        }
    }
    return $size;
}

$path = req('path');
if ($path === null) tres('need POST{path = (...)}');
$dir = resolve_path($path);
if (!is_dir($dir)) tres('dir not found: ' . $dir, 404);

$out = [];
foreach (scandir($dir) ?: [] as $n) {
    if ($n === '.' || $n === '..') continue;
    $full = $dir . '/' . $n;
    if (!is_dir($full)) continue;
    $out[] = [
        'name' => $n,
        'size_bytes' => dir_size_bytes($full),
    ];
}

jres($out);
