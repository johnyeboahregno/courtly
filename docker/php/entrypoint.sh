#!/bin/sh
set -e

# The named volume mounted over storage/ can come up owned by root on first
# creation — re-assert ownership every start rather than only at build time.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"
