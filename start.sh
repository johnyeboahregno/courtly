#!/usr/bin/env bash
cd "$(dirname "$0")"
php artisan serve --host=0.0.0.0 --port=8000
