<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

$path = req('path');
if ($path === null) tres('need POST{path}!');
$p = resolve_path($path);
if (is_dir($p)) tres('dir');
if (is_file($p)) tres('file');
tres('閰脩隞嗡?摮: ' . $p, 404);


