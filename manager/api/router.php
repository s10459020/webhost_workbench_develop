<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$cfgFile = __DIR__ . '/../config/server.json';
if (!is_file($cfgFile)) {
  http_response_code(500);
  echo 'server config not found';
  exit;
}

$cfgRaw = file_get_contents($cfgFile);
$cfg = json_decode($cfgRaw ?: '{}', true);
$apiVersion = isset($cfg['api_version']) ? (string)$cfg['api_version'] : '';
if ($apiVersion !== 'php74' && $apiVersion !== 'php84') {
  http_response_code(500);
  echo 'invalid api_version';
  exit;
}

$endpoint = isset($_REQUEST['__endpoint']) ? (string)$_REQUEST['__endpoint'] : '';
$endpoint = ltrim(str_replace('\\', '/', $endpoint), '/');
if ($endpoint === '' || strpos($endpoint, '..') !== false) {
  http_response_code(400);
  echo 'invalid endpoint';
  exit;
}

if (strpos($endpoint, 'ftp/') === 0) {
  $osDir = (stripos(PHP_OS_FAMILY, 'Windows') !== false) ? 'osWin' : 'osLinux';
  $osRouter = __DIR__ . '/' . $osDir . '/router.php';
  if (!is_file($osRouter)) {
    http_response_code(500);
    echo 'os router not found: ' . $osDir;
    exit;
  }
  $_REQUEST['__endpoint_os'] = $endpoint;
  require $osRouter;
  exit;
}

$primary = __DIR__ . '/' . $apiVersion . '/' . $endpoint;
$target = $primary;

if ($apiVersion === 'php84' && !is_file($primary)) {
  $fallback = __DIR__ . '/php74/' . $endpoint;
  if (is_file($fallback)) {
    $target = $fallback;
  }
}

if (!is_file($target)) {
  http_response_code(404);
  echo 'api endpoint not found: ' . $endpoint;
  exit;
}

require $target;
