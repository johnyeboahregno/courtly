#!/usr/bin/env bash
#
# Courtly — full local test run.
#
# Runs preflight checks, then the complete Pest suite against the in-memory
# SQLite database configured in phpunit.xml. No server, no MySQL and no
# network access are required, so this works completely offline.
#
# Extra Pest arguments are passed straight through, e.g.
#   ./tools/local-test.sh --filter=Matchmaking
#   ./tools/local-test.sh tests/Feature/Local/OfflineFlowTest.php
#
set -uo pipefail

cd "$(dirname "$0")/.."

failed=0

echo "── Preflight ─────────────────────────────────────────"

if php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
  echo "✓ PHP $(php -r 'echo PHP_VERSION;')"
else
  echo "✗ PHP 8.3 or newer is required"; failed=1
fi

for ext in pdo_sqlite mbstring openssl curl; do
  if php -m | grep -qix "$ext"; then
    echo "✓ extension $ext"
  else
    echo "✗ missing PHP extension: $ext"; failed=1
  fi
done

if [ -d vendor ]; then
  echo "✓ vendor/ installed"
else
  echo "✗ vendor/ missing — run: php composer.phar install"; failed=1
fi

if [ -f vendor/bin/pest ]; then
  echo "✓ Pest available"
else
  echo "✗ vendor/bin/pest missing"; failed=1
fi

if [ "$failed" -ne 0 ]; then
  echo
  echo "Preflight failed — fix the items above, then run this script again."
  exit 1
fi

echo
echo "── Test suite (in-memory SQLite, sync queue) ─────────"
echo

php vendor/bin/pest "$@"
status=$?

echo
if [ "$status" -eq 0 ]; then
  echo "✓ All tests passed."
else
  echo "✗ Tests failed (exit code $status)."
fi

exit "$status"
