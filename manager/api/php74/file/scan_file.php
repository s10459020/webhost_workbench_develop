<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

$path = req('path');
if ($path === null) tres('need POST{path = (...)}');
$dir = resolve_path($path);
if (!is_dir($dir)) tres('dir not found: ' . $dir, 404);

$out = [];
foreach (scandir($dir) ?: [] as $n) {
    if ($n === '.' || $n === '..') continue;
    $full = $dir . '/' . $n;
    if (!is_file($full)) continue;
    $size = filesize($full);
    $out[] = [
        'name' => $n,
        'size_bytes' => $size === false ? 0 : $size,
    ];
}

jres($out);
