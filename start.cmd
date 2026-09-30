@echo off
cd /d "%~dp0"
set "PROJECT_PHP=C:\xampp\php\php.exe"
if not exist "%PROJECT_PHP%" set "PROJECT_PHP=php"
echo RentFlow: http://127.0.0.1:8021
"%PROJECT_PHP%" artisan serve --host=127.0.0.1 --port=8021
pause
