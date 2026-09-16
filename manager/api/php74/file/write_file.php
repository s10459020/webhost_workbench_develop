<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
$path = req('path');
$content = req('content');
if ($path === null) tres('need POST{path}!');
if ($content === null) tres('need POST{content}!');
$file = resolve_path($path);
if (!is_file($file)) tres($path . ' is not a file', 404);
$content = str_replace(['&lt;', '&gt;'], ['<', '>'], $content);
if (!write_file_open($file, $content)) tres('write fail: ' . $file, 500);
tres('ok');
