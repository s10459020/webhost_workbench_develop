<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
require_once __DIR__ . '/../api_lib/download_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tres('method not allowed', 405);
$baseInput = req('base');
$filesJson = req('file_paths');
if ($baseInput === null || $baseInput === '') tres('need POST{base}!', 400);
if ($filesJson === null || $filesJson === '') tres('need POST{file_paths}!', 400);

$base = resolve_path($baseInput);
if (!is_dir($base)) tres('base is not a directory: ' . $baseInput, 404);
$rows = json_decode($filesJson, true);
if (!is_array($rows)) tres('file_paths must be json array', 400);

download_files_from_rows($base, $rows);
