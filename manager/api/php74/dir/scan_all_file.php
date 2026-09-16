<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tres('method not allowed', 405);
$path = req('path');
if ($path === null) tres('need POST{path}!');

$dir = resolve_path($path);
if (!is_dir($dir)) tres($path . ' is not a directory', 404);

$base = rtrim(str_replace('\\', '/', $dir), '/');
$baseLen = strlen($base) + 1;
$rows = [];

try {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $node) {
        if (!$node->isFile()) continue;
        $abs = str_replace('\\', '/', $node->getPathname());
        $rel = substr($abs, $baseLen);
        if ($rel === false) continue;
        $rows[] = [
            'path' => $rel,
            'mtime' => (int)$node->getMTime(),
            'hash' => hash_file('sha256', $abs) ?: '',
        ];
    }
} catch (Throwable $e) {
    tres('scan_all_file error: ' . $e->getMessage() . ' (path=' . $dir . ')', 500);
}

usort($rows, fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
jres($rows);
