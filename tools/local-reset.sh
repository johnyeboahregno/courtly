#!/usr/bin/env bash
#
# Courtly — reset the LOCAL development database (SQLite) and reseed it.
#
# Safe by design: it refuses to run unless .env points at SQLite, so it can
# never wipe the VPS MySQL database. Run it whenever you want a clean slate.
#
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  echo "✗ .env is missing." >&2
  echo "  Copy .env.example to .env (local/SQLite setup) and try again." >&2
  exit 1
fi

if ! grep -qE '^DB_CONNECTION=sqlite' .env; then
  echo "✗ Refusing to run: .env does not set DB_CONNECTION=sqlite." >&2
  echo "  This guard exists so a local reset can never hit a remote/production DB." >&2
  exit 1
fi

echo "── Rebuilding the local SQLite database ──────────────"
php artisan config:clear >/dev/null
php artisan migrate:fresh --force
php artisan db:seed --class=DevelopmentSeeder --force

echo
echo "✓ Local database ready."
echo "  Login:    organiser@courtly.test / password"
echo "  Superadmin: admin@regno.ai (see database/seeders/SuperAdminSeeder.php)"
echo "  Next:     ./start.sh   (serve + queue worker) then open http://localhost:8000"
