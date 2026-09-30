@echo off
cd /d "%~dp0"
set "PROJECT_PHP=C:\xampp\php\php.exe"
if not exist "%PROJECT_PHP%" set "PROJECT_PHP=php"
"%PROJECT_PHP%" artisan schedule:work
pause
