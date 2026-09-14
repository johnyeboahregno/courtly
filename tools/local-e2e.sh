#!/usr/bin/env bash
#
# Courtly — offline browser end-to-end tests (Playwright).
#
# Self-contained: a dedicated SQLite database, the PHP built-in server and
# local assets only. No internet, MySQL or VPS required.
#
# Extra Playwright arguments are passed straight through, e.g.
#   ./tools/local-e2e.sh --headed
#   ./tools/local-e2e.sh tests/e2e/session-flow.spec.ts
#
set -euo pipefail

cd "$(dirname "$0")/.."

# Dedicated database so E2E runs never touch your dev database.
export COURTLY_E2E_DB="$(pwd)/database/courtly-e2e.sqlite"

if [ ! -d node_modules ]; then
  echo "── Installing Playwright (one-time) ──────────────────"
  npm install
fi

# Browsers are usually already cached. Only needed once, and only if missing.
if [ ! -d "${HOME}/AppData/Local/ms-playwright" ] && [ ! -d "${HOME}/.cache/ms-playwright" ]; then
  echo "── Installing the Chromium browser (one-time) ────────"
  npx playwright install chromium
fi

echo "── Running browser E2E (offline) ─────────────────────"
npx playwright test "$@"
