<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tres('method not allowed', 405);

$apiVersion = strtolower(trim((string)req('api_version', '')));
$configPath = path_join(root_dir(), 'manager/config/server.json');
$json = json_encode(['api_version' => $apiVersion], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";

write_file_open($configPath, $json);
jres(['ok' => true, 'api_version' => $apiVersion]);
