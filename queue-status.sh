#!/usr/bin/env bash
# Shows production worker health and a read-only snapshot of the Laravel queue.
set -Eeuo pipefail

cd "$(dirname "$0")"

compose=(docker compose -f docker-compose.yml -f docker-compose.prod.yml)

printf 'Workers\n'
"${compose[@]}" ps --all --format 'table {{.Service}}\t{{.Status}}' \
    queue onboarding-queue analysis-queue interactive-queue

printf '\nQueue\n'
"${compose[@]}" exec -T app php artisan personal:queue-status "$@"
