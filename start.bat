@echo off
setlocal
REM Start the signing service and PHP dev server on the first free ports.
if not defined APP_PORT set "APP_PORT=18180"
if not defined A_BOGUS_PORT set "A_BOGUS_PORT=9876"
REM php -S 默认单 worker，一次慢解析会阻塞全部请求
if not defined PHP_CLI_SERVER_WORKERS set "PHP_CLI_SERVER_WORKERS=8"
set "SCRIPT_DIR=%~dp0"

for /f "delims=" %%P in ('powershell -NoProfile -Command "$p=[int]$env:APP_PORT; while(Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue){$p++}; $p"') do set "APP_PORT=%%P"
for /f "delims=" %%P in ('powershell -NoProfile -Command "$p=[int]$env:A_BOGUS_PORT; while(Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue){$p++}; $p"') do set "A_BOGUS_PORT=%%P"

set "PHP_BIN=php"
for /f "delims=" %%P in ('where php 2^>nul') do set "PHP_BIN=%%P"
set "PHP_ARGS="
"%PHP_BIN%" -m 2>nul | findstr /I /R /C:"^curl$" >nul
if errorlevel 1 (
  for %%D in ("%PHP_BIN%") do set "PHP_DIR=%%~dpD"
  if exist "%PHP_DIR%ext\php_curl.dll" set "PHP_ARGS=-d extension_dir=%PHP_DIR%ext -d extension=curl -d extension=openssl"
)

set "A_BOGUS_PORT=%A_BOGUS_PORT%"
start "a_bogus" /B cmd /c "set A_BOGUS_PORT=%A_BOGUS_PORT%&& node ""%SCRIPT_DIR%scripts\a_bogus_server.js"""
echo a_bogus server started on port %A_BOGUS_PORT%
echo PHP server starting on http://127.0.0.1:%APP_PORT%

"%PHP_BIN%" %PHP_ARGS% -S 127.0.0.1:%APP_PORT% -t "%SCRIPT_DIR%public" "%SCRIPT_DIR%public\router.php"
