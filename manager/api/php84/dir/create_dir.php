<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';
$dir = req('dir');
if ($dir === null) tres('need POST{dir}!');
$base = resolve_path($dir);
if (!create_dir_open($base)) tres('mkdir fail: ' . $base . last_error_text(), 500);
$name = req('name');
if ($name === null) tres('need POST{name}!', 400);
$name = trim($name);
if ($name === '') tres('name cannot be empty', 400);
if (str_contains($name, '/') || str_contains($name, '\\')) {
    tres('name cannot contain path separator', 400);
}
if (is_dir(path_join($base, $name))) tres('dir already exist', 409);
$target = path_join($base, $name);
if (!create_dir_open($target)) {
    tres('mkdir fail: ' . $target . last_error_text(), 500);
}
tres('create [' . rel_from_root($target) . '/]');
