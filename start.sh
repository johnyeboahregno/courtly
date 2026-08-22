#!/usr/bin/env bash
cd "$(dirname "$0")"

# Clear stale route cache (prevents 405 Method Not Allowed)
rm -f bootstrap/cache/routes-v7.php

php artisan serve --host=0.0.0.0 --port=8000
