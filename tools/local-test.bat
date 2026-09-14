@echo off
REM Courtly - full local test run.
REM
REM Runs preflight checks, then the complete Pest suite against the in-memory
REM SQLite database from phpunit.xml. No server, no MySQL and no network access
REM required, so this works completely offline.
REM
REM Extra Pest arguments are passed straight through:
REM   tools\local-test.bat --filter=Matchmaking
REM   tools\local-test.bat tests\Feature\Local\OfflineFlowTest.php

cd /d "%~dp0.."

set failed=0

echo -- Preflight -----------------------------------------

php -r "exit(PHP_VERSION_ID >= 80300 ? 0 : 1);" 2>nul
if errorlevel 1 (
  echo x PHP 8.3 or newer is required
  set failed=1
) else (
  for /f "delims=" %%v in ('php -r "echo PHP_VERSION;"') do echo OK - PHP %%v
)

for %%e in (pdo_sqlite mbstring openssl curl) do (
  php -m | findstr /i /x "%%e" >nul
  if errorlevel 1 (
    echo x missing PHP extension: %%e
    set failed=1
  ) else (
    echo OK - extension %%e
  )
)

if exist vendor (echo OK - vendor/ installed) else (echo x vendor/ missing - run: php composer.phar install & set failed=1)
if exist vendor\bin\pest.bat (echo OK - Pest available) else (echo x vendor\bin\pest.bat missing & set failed=1)

if not "%failed%"=="0" (
  echo.
  echo Preflight failed - fix the items above, then run this script again.
  exit /b 1
)

echo.
echo -- Test suite (in-memory SQLite, sync queue) ---------
echo.

call vendor\bin\pest.bat %*
set status=%errorlevel%

echo.
if "%status%"=="0" (
  echo OK - All tests passed.
) else (
  echo x Tests failed ^(exit code %status%^).
)

exit /b %status%
