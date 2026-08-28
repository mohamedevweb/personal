#!/usr/bin/env bash
# Deploys the backend after a successful GitHub Actions run on main.
set -Eeuo pipefail

cd /opt/personal

compose_files=(-f docker-compose.yml -f docker-compose.prod.yml)
backend_services=(app web queue onboarding-queue analysis-queue interactive-queue scheduler)

git fetch origin main --quiet
git reset --hard origin/main

docker compose "${compose_files[@]}" build "${backend_services[@]}"
docker compose "${compose_files[@]}" up -d postgres "${backend_services[@]}" caddy

# Nginx resolves the PHP container address when it starts. Restarting it after
# the app avoids retaining the address of the container replaced by Compose.
docker compose "${compose_files[@]}" restart web

for attempt in $(seq 1 30); do
    if curl --fail --silent --show-error --output /dev/null --max-time 10 \
        https://api.usepersonal.app/; then
        docker compose "${compose_files[@]}" ps
        exit 0
    fi

    sleep 2
done

echo 'The API did not become healthy after deployment.' >&2
docker compose "${compose_files[@]}" ps
exit 1
