<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
$path = req('path');
if ($path === null) tres('need POST{path}!');
$file = resolve_path($path);
if (!is_file($file)) tres($path . ' is not a file', 404);
$content = file_get_contents($file);
if ($content === false) tres('read fail', 500);
header('Content-Type: text/plain; charset=utf-8');
echo $content;


