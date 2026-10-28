#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f .env ]; then cp .env.example .env; fi
docker compose build app
docker compose up -d --wait db
# Respect inherited proxy and CA trust, including inside the PHP container.
ca_bundle="${SURVIVAL_CA_BUNDLE:-/etc/ssl/certs/ca-certificates.crt}"
docker compose run --rm -u "$(id -u):$(id -g)" -v "$ca_bundle:/run/setup-ca.pem:ro" -e COMPOSER_CAFILE=/run/setup-ca.pem -e GIT_SSL_CAINFO=/run/setup-ca.pem -e COMPOSER_HOME=/tmp/composer app composer install --no-interaction --prefer-source
if ! grep -q '^APP_KEY=base64:' .env; then docker compose run --rm -u "$(id -u):$(id -g)" app php artisan key:generate; fi
npm ci
npm run build
docker compose run --rm -u "$(id -u):$(id -g)" app php artisan migrate --seed --force
docker compose up -d app
