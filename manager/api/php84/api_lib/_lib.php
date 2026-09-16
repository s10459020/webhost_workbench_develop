<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', '0');

set_exception_handler(function (Throwable $e): void {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    exit;
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err === null) return;
    if (!in_array((int)$err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
});

function root_dir(): string {
    static $root = null;
    if ($root !== null) return $root;
    // Workspace root: project root (SHV)
    $path = realpath(__DIR__ . '/..' . '/..' . '/..' . '/..');
    if ($path === false) {
        $path = __DIR__ . '/..' . '/..' . '/..' . '/..';
    }
    $root = rtrim(str_replace('\\', '/', (string)$path), '/');
    return $root;
}

function req(string $key, ?string $default = null): ?string {
    if (isset($_POST[$key])) return (string)$_POST[$key];
    if (isset($_GET[$key])) return (string)$_GET[$key];
    return $default;
}

function jres($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tres(string $text, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

function last_error_text(): string {
    $err = error_get_last();
    return $err === null ? '' : ': ' . $err['message'];
}

function open_permissions(string $path): void {
    @chmod($path, 0777);
}

function open_permissions_to_root(string $path): void {
    $root = root_dir();
    $raw = rtrim(str_replace('\\', '/', $path), '/');
    if ($raw === '') return;
    if (strpos($raw, $root) !== 0) {
        open_permissions($raw);
        return;
    }
    $rel = trim(substr($raw, strlen($root)), '/');
    $cur = $root;
    open_permissions($cur);
    if ($rel === '') return;
    foreach (explode('/', $rel) as $part) {
        if ($part === '') continue;
        $cur = path_join($cur, $part);
        open_permissions($cur);
    }
}

function create_dir_open(string $path): bool {
    if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) return false;
    open_permissions_to_root($path);
    return true;
}

function write_file_open(string $path, string $content): bool {
    $ok = @file_put_contents($path, $content);
    if ($ok === false) return false;
    open_permissions_to_root($path);
    return true;
}

function move_uploaded_file_open(string $tmp, string $target, int $mtime = 0): bool {
    open_permissions_to_root(dirname($target));
    if (is_file($target)) {
        $in = @fopen($tmp, 'rb');
        $out = @fopen($target, 'wb');
        if ($in !== false && $out !== false) {
            $ok = @stream_copy_to_stream($in, $out) !== false;
            @fclose($in);
            @fclose($out);
            if ($ok) {
                if ($mtime > 0) @touch($target, $mtime);
                open_permissions_to_root($target);
                return true;
            }
        } else {
            if ($in !== false) @fclose($in);
            if ($out !== false) @fclose($out);
        }
    }
    $tempTarget = path_join(dirname($target), '.upload-' . uniqid('', true) . '.tmp');
    if (!@move_uploaded_file($tmp, $tempTarget)) return false;
    open_permissions_to_root($tempTarget);
    if ($mtime > 0) @touch($tempTarget, $mtime);
    if (!@rename($tempTarget, $target)) {
        @unlink($tempTarget);
        return false;
    }
    open_permissions_to_root($target);
    return true;
}

function rename_path_open(string $old, string $new): bool {
    if (!@rename($old, $new)) return false;
    open_permissions_to_root($new);
    return true;
}

function normalize_physical_path(string $input): string {
    $raw = str_replace('\\', '/', trim($input));
    if ($raw === '') tres('path must be physical absolute path', 400);
    if (preg_match('/^[A-Za-z]:\//', $raw) === 1) {
        $raw = '/' . $raw;
    }
    if (!str_starts_with($raw, '/')) {
        tres('path must be physical absolute path: ' . $input, 400);
    }

    $kind = 'unix';
    $prefix = '/';
    $rest = substr($raw, 1);
    if (preg_match('/^([A-Za-z]:)(?:\/(.*)|$)/', $rest, $m) === 1) {
        $kind = 'drive';
        $prefix = strtoupper($m[1]) . '/';
        $rest = isset($m[2]) ? (string)$m[2] : '';
    }

    $parts = [];
    foreach (explode('/', $rest) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            if (count($parts) > 0) array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    if ($kind === 'drive') {
        return count($parts) > 0 ? $prefix . implode('/', $parts) : $prefix;
    }
    return count($parts) > 0 ? '/' . implode('/', $parts) : '/';
}

function resolve_path(string $input): string {
    return normalize_physical_path($input);
}

function to_api_path(string $physical): string {
    $raw = str_replace('\\', '/', trim($physical));
    if ($raw === '') return '/';
    if (preg_match('/^[A-Za-z]:\//', $raw) === 1) return '/' . $raw;
    return str_starts_with($raw, '/') ? $raw : '/' . $raw;
}

function rel_from_root(string $abs): string {
    return to_api_path($abs);
}

function path_join(string $base, string $name): string {
    $base = str_replace('\\', '/', $base);
    if (preg_match('/^[A-Za-z]:\/$/', $base) || $base === '/') {
        return $base . ltrim($name, '/');
    }
    return rtrim($base, '/') . '/' . ltrim($name, '/');
}

