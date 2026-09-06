#!/bin/sh
# Run this ON THE OVH VPS, from the project directory (next to docker-compose.yml),
# after `docker compose up -d` has the mysql/app/queue/caddy containers running.
#
# Dumps the live source database and restores it into this stack's `mysql`
# service. Uses --single-transaction so it takes a consistent snapshot without
# locking tables on the source — safe to run as a rehearsal against a live
# database with no impact on it.
#
# Usage:
#   SOURCE_HOST=db2.regnocloud.com SOURCE_USER=admin SOURCE_PASS='...' SOURCE_DB=courtly \
#     ./docker/mysql/migrate-from-regnocloud.sh
#
# Nothing is installed permanently on the VPS — mysqldump runs via a throwaway
# `mysql:8.4` container.

set -eu

: "${SOURCE_HOST:?set SOURCE_HOST}"
: "${SOURCE_USER:?set SOURCE_USER}"
: "${SOURCE_PASS:?set SOURCE_PASS}"
: "${SOURCE_DB:?set SOURCE_DB}"

DUMP_FILE="/tmp/courtly-migration-$(date +%Y%m%d%H%M%S).sql"

echo "Dumping $SOURCE_DB from $SOURCE_HOST -> $DUMP_FILE"
docker run --rm mysql:8.4 \
  mysqldump -h "$SOURCE_HOST" -u "$SOURCE_USER" -p"$SOURCE_PASS" \
    --single-transaction --quick --routines --triggers "$SOURCE_DB" \
  > "$DUMP_FILE"

echo "Restoring into this stack's 'mysql' service"
docker compose exec -T mysql sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < "$DUMP_FILE"

echo "Done. Dump kept at $DUMP_FILE — verify row counts, then delete it once satisfied."
