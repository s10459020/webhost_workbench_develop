@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "ROOT=%~dp0"
set "PHP_EXE=%ROOT%.tool\php-8.4.20-nts-Win32-vs17-x64\php.exe"
set "HOST=0.0.0.0"
set "PORT=%~1"
set "UPLOAD_MAX=1024M"
set "POST_MAX=1024M"
set "MAX_UPLOADS=200"
set "MAX_EXEC=600"
set "MAX_INPUT=600"
set "MEM_LIMIT=1024M"

if "%PORT%"=="" set "PORT=8000"
set "SERVER_CONFIG=%ROOT%manager\config\server.json"

(
  echo {
  echo   "api_version": "php84"
  echo }
) > "%SERVER_CONFIG%"

echo.
echo PHP 8.4 server is starting...
echo Root   : %ROOT%
echo PHP    : %PHP_EXE%
echo Bind   : http://%HOST%:%PORT%
echo Local  : http://127.0.0.1:%PORT%
echo upload_max_filesize : %UPLOAD_MAX%
echo post_max_size       : %POST_MAX%
echo max_file_uploads    : %MAX_UPLOADS%
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
  -S %HOST%:%PORT% -t .
popd

endlocal
