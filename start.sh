#!/usr/bin/env bash
#
# Courtly — start the whole application locally.
#
# Two processes are required:
#   1. the web server (php artisan serve)
#   2. the queue worker — court re-allocation after a recorded result is
#      dispatched as a queued job, so without it courts never refill.
#
set -uo pipefail

cd "$(dirname "$0")"

if [ ! -f .env ]; then
  echo "✗ .env is missing — copy .env.example to .env first." >&2
  exit 1
fi

# Stale route cache (prevents 405 Method Not Allowed)
rm -f bootstrap/cache/routes-v7.php

# Make sure the local SQLite database exists and is up to date (idempotent).
if grep -qE '^DB_CONNECTION=sqlite' .env; then
  db_path="$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '\r"')"
  if [ -n "$db_path" ] && [ ! -f "$db_path" ]; then
    mkdir -p "$(dirname "$db_path")"
    : > "$db_path"
    echo "Created $db_path"
  fi
fi

php artisan migrate --force

# Queue worker in the background, web server in the foreground. Ctrl+C stops both.
php artisan queue:work database --sleep=1 --tries=2 &
worker_pid=$!
trap 'kill "$worker_pid" 2>/dev/null' EXIT INT TERM

echo "Starting Courtly on http://localhost:8000 ..."
php artisan serve --host=0.0.0.0 --port=8000
