#!/bin/sh
set -e

# The app, queue and scheduler containers share this entrypoint. Only the one
# that sets RUN_MIGRATIONS touches the schema, so concurrent containers never
# race on it.

if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running database migrations…"
    php artisan migrate --force
fi

# Caches are rebuilt per container start so a changed environment is always
# reflected, and are removed first in case an image shipped a stale cache.
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
