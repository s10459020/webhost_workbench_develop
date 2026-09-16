<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
require_once __DIR__ . '/../api_lib/download_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tres('method not allowed', 405);
$pathInput = req('path');
if ($pathInput === null || $pathInput === '') tres('need POST{path}!', 400);

$dir = resolve_path($pathInput);
if (!is_dir($dir)) tres('base is not a directory: ' . $pathInput, 404);
download_dir_zip($dir);
