<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
$path = req('path');
if ($path === null) tres('need POST{path}!');
$dir = resolve_path($path);
if (!is_dir($dir)) tres('skip [' . $path . '/]');

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $node) {
    if ($node->isDir()) {
        $p = $node->getPathname();
        if (!@rmdir($p)) tres('delete dir fail: ' . $p, 500);
    } else {
        $p = $node->getPathname();
        if (!@unlink($p)) tres('delete file fail: ' . $p, 500);
    }
}
if (!@rmdir($dir)) tres('delete dir fail: ' . $dir, 500);
tres('delete [' . rel_from_root($dir) . '/]');
