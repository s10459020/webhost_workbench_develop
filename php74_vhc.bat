@echo off
setlocal EnableExtensions DisableDelayedExpansion

set "ROOT=%~dp0"
set "PHP_EXE=%ROOT%.tool\php-7.4.33-nts-Win32-vc15-x64\php.exe"
set "ROUTER=%TEMP%\vhc_router_%RANDOM%_%RANDOM%.php"
set "HOST=0.0.0.0"
set "PORT=%~1"
set "UPLOAD_MAX=1024M"
set "POST_MAX=1024M"
set "MAX_UPLOADS=200"
set "MAX_EXEC=600"
set "MAX_INPUT=600"
set "MEM_LIMIT=1024M"
set "SERVER_CONFIG=%ROOT%manager\config\server.json"

if "%PORT%"=="" set "PORT=8000"

(
  echo ^<?php
  echo declare^(strict_types=1^);
  echo $docRoot = '%ROOT:\=/%';
  echo $docRoot = rtrim^($docRoot, '/'^);
  echo $uriPath = parse_url^($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH^);
  echo $path = rawurldecode^(is_string^($uriPath^) ? $uriPath : '/'^);
  echo $prefix = '/vhc';
  echo if ^(substr^($path, 0, strlen^($prefix^)^) !== $prefix^) ^{
  echo     http_response_code^(404^);
  echo     header^('Content-Type: text/plain; charset=utf-8'^);
  echo     echo 'use /vhc/* path';
  echo     exit;
  echo ^}
  echo $rel = substr^($path, strlen^($prefix^)^);
  echo if ^($rel === ''^) $rel = '/';
  echo $target = $docRoot . $rel;
  echo if ^(is_dir^($target^)^) $target = rtrim^($target, '/\\'^) . DIRECTORY_SEPARATOR . 'index.html';
  echo if ^(!is_file^($target^)^) ^{
  echo     http_response_code^(404^);
  echo     header^('Content-Type: text/plain; charset=utf-8'^);
  echo     echo 'not found';
  echo     exit;
  echo ^}
  echo $ext = strtolower^((string^)pathinfo^($target, PATHINFO_EXTENSION^)^);
  echo if ^($ext === 'php'^) ^{
  echo     $_SERVER['SCRIPT_NAME'] = $rel;
  echo     $_SERVER['PHP_SELF'] = $rel;
  echo     $_SERVER['SCRIPT_FILENAME'] = $target;
  echo     require $target;
  echo     exit;
  echo ^}
  echo if ^($ext === 'html'^) header^('Content-Type: text/html; charset=utf-8'^);
  echo else if ^($ext === 'css'^) header^('Content-Type: text/css; charset=utf-8'^);
  echo else if ^($ext === 'js'^) header^('Content-Type: application/javascript; charset=utf-8'^);
  echo else if ^($ext === 'json'^) header^('Content-Type: application/json; charset=utf-8'^);
  echo else if ^($ext === 'txt'^) header^('Content-Type: text/plain; charset=utf-8'^);
  echo else if ^($ext === 'svg'^) header^('Content-Type: image/svg+xml'^);
  echo else if ^($ext === 'png'^) header^('Content-Type: image/png'^);
  echo else if ^($ext === 'jpg' ^|^| $ext === 'jpeg'^) header^('Content-Type: image/jpeg'^);
  echo else if ^($ext === 'gif'^) header^('Content-Type: image/gif'^);
  echo else if ^($ext === 'webp'^) header^('Content-Type: image/webp'^);
  echo else if ^($ext === 'ico'^) header^('Content-Type: image/x-icon'^);
  echo else if ^($ext === 'zip'^) header^('Content-Type: application/zip'^);
  echo else if ^($ext === 'gz'^) header^('Content-Type: application/gzip'^);
  echo else header^('Content-Type: application/octet-stream'^);
  echo header^('Content-Length: ' . filesize^($target^)^);
  echo readfile^($target^);
  echo exit;
) > "%ROUTER%"

(
  echo {
  echo   "api_version": "php74"
  echo }
) > "%SERVER_CONFIG%"

echo.
echo PHP 7.4 /vhc router server is starting...
echo Root   : %ROOT%
echo PHP    : %PHP_EXE%
echo URL    : http://127.0.0.1:%PORT%/vhc/index.html
echo.
echo Press Ctrl+C to stop.
echo.

pushd "%ROOT%"
"%PHP_EXE%" ^
  -d default_charset=UTF-8 ^
  -d upload_max_filesize=%UPLOAD_MAX% ^
  -d post_max_size=%POST_MAX% ^
  -d max_file_uploads=%MAX_UPLOADS% ^
  -d max_execution_time=%MAX_EXEC% ^
  -d max_input_time=%MAX_INPUT% ^
  -d memory_limit=%MEM_LIMIT% ^
  -S %HOST%:%PORT% -t . "%ROUTER%"
popd

if exist "%ROUTER%" del /f /q "%ROUTER%" >nul 2>nul
endlocal
