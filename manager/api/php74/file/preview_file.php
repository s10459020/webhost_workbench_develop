<?php
declare(strict_types=1);
require_once __DIR__ . '/../api_lib/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') tres('method not allowed', 405);
$path = isset($_GET['path']) ? (string)$_GET['path'] : null;
if ($path === null || $path === '') tres('need GET{path}!');
$file = resolve_path($path);
if (!is_file($file)) tres($path . ' is not a file', 404);

$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($file);
    if (is_string($detected) && $detected !== '') {
        $mime = $detected;
    }
}
if ($mime === 'application/octet-stream') {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeMap = [
        'gif' => 'image/gif',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'bmp' => 'image/bmp',
        'ico' => 'image/x-icon',
        'mov' => 'video/quicktime',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/markdown; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8'
    ];
    if (isset($mimeMap[$ext])) $mime = $mimeMap[$ext];
}

$size = filesize($file);
$start = 0;
$end = $size > 0 ? $size - 1 : 0;
$hasRange = false;
$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/i', $range, $m)) {
    $hasRange = true;
    if ($m[1] !== '') $start = (int)$m[1];
    if ($m[2] !== '') $end = (int)$m[2];
    if ($m[1] === '' && $m[2] !== '') {
        $suffix = (int)$m[2];
        if ($suffix > 0 && $size > 0) {
            $start = max(0, $size - $suffix);
            $end = $size - 1;
        }
    }
    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $end = min($end, $size - 1);
}

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$length = $end - $start + 1;
if ($hasRange) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . $length);

$fp = fopen($file, 'rb');
if ($fp === false) tres('open file fail', 500);
fseek($fp, $start);
$remain = $length;
while ($remain > 0 && !feof($fp)) {
    $chunk = fread($fp, min(8192, $remain));
    if ($chunk === false) break;
    echo $chunk;
    $remain -= strlen($chunk);
}
fclose($fp);
exit;


