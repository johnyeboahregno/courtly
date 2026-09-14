@echo off
REM Courtly - reset the LOCAL development database (SQLite) and reseed it.
REM
REM Safe by design: it refuses to run unless .env points at SQLite, so it can
REM never wipe the VPS MySQL database.

cd /d "%~dp0.."

if not exist ".env" (
  echo x .env is missing.
  echo   Copy .env.example to .env ^(local/SQLite setup^) and try again.
  exit /b 1
)

findstr /b /c:"DB_CONNECTION=sqlite" .env >nul
if errorlevel 1 (
  echo x Refusing to run: .env does not set DB_CONNECTION=sqlite.
  echo   This guard exists so a local reset can never hit a remote/production DB.
  exit /b 1
)

echo -- Rebuilding the local SQLite database --------------
php artisan config:clear >nul
php artisan migrate:fresh --force || exit /b 1
php artisan db:seed --class=DevelopmentSeeder --force || exit /b 1

echo.
echo OK - Local database ready.
echo   Login:      organiser@courtly.test / password
echo   Superadmin: admin@regno.ai (see database/seeders/SuperAdminSeeder.php)
echo   Next:       start.bat  then open http://localhost:8000
