@echo off
REM Courtly — Windows dev server launcher
REM Double-click this file, or run `start.bat` from PowerShell/CMD.

cd /d "%~dp0"

REM Clear stale route cache (prevents 405 Method Not Allowed)
if exist "bootstrap\cache\routes-v7.php" del /q "bootstrap\cache\routes-v7.php"

echo Starting Courtly on http://localhost:8000 ...
start "Courtly Queue" /B php artisan queue:work database --sleep=1 --tries=2
php artisan serve --host=0.0.0.0 --port=8000
