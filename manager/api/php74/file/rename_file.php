<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
$path = req('path');
$name = req('name');
if ($path === null) tres('need POST{path}!');
if ($name === null || trim($name) === '') tres('need POST{name}!');
$file = resolve_path($path);
if (!is_file($file)) tres($file . ' is not a file', 404);
$new = resolve_path(path_join(dirname($file), trim($name)));
if (is_file($new)) tres('file already exist', 409);
if (!rename_path_open($file, $new)) tres('rename fail: ' . $file . ' to ' . $new, 500);
tres('rename [' . rel_from_root($file) . '] to [' . rel_from_root($new) . ']');
