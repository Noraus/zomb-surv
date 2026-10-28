#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec -T -e DB_DATABASE=survival_test -e APP_ENV=testing app php tests/concurrency.php
