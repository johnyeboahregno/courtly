@echo off
REM Courtly - offline browser end-to-end tests (Playwright).
REM
REM Self-contained: a dedicated SQLite database, the PHP built-in server and
REM local assets only. No internet, MySQL or VPS required.
REM
REM Extra Playwright arguments are passed straight through:
REM   tools\local-e2e.bat --headed
REM   tools\local-e2e.bat tests\e2e\session-flow.spec.ts

cd /d "%~dp0.."

REM Dedicated database so E2E runs never touch your dev database.
set "COURTLY_E2E_DB=%CD%\database\courtly-e2e.sqlite"

if not exist node_modules (
  echo -- Installing Playwright ^(one-time^) ------------------
  call npm install || exit /b 1
)

if not exist "%USERPROFILE%\AppData\Local\ms-playwright" (
  echo -- Installing the Chromium browser ^(one-time^) --------
  call npx playwright install chromium || exit /b 1
)

echo -- Running browser E2E ^(offline^) ---------------------
call npx playwright test %*
exit /b %errorlevel%
