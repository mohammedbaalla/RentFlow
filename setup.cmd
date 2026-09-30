@echo off
cd /d "%~dp0"
set "PROJECT_PHP=C:\xampp\php\php.exe"
if not exist "%PROJECT_PHP%" set "PROJECT_PHP=php"
if exist vendor\autoload.php goto prepare
call composer install --no-dev --no-interaction
if errorlevel 1 exit /b 1
:prepare
"%PROJECT_PHP%" scripts\setup.php
pause
