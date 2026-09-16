<?php
declare(strict_types=1);

$endpoint = isset($_REQUEST['__endpoint_os']) ? (string)$_REQUEST['__endpoint_os'] : '';
$endpoint = ltrim(str_replace('\\', '/', $endpoint), '/');
if ($endpoint === '' || strpos($endpoint, '..') !== false) {
  http_response_code(400);
  echo 'invalid os endpoint';
  exit;
}

$target = __DIR__ . '/' . $endpoint;
if (!is_file($target)) {
  http_response_code(404);
  echo 'os api endpoint not found: ' . $endpoint;
  exit;
}

require $target;
