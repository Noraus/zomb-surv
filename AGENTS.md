# Última señal

Use the existing checkout: each cloud task is already isolated; do not create a worktree unless requested.
PHP and Composer run inside `docker compose run --rm -u "$(id -u):$(id -g)" app …` (PHP 8.4). Laravel Boost is installed as a development dependency.
Read README.md for setup and docs/architecture.md for invariants.
Use `bash scripts/setup.sh`, then `docker compose up -d app`.
Run `docker compose exec -T app php artisan test --compact`, `bash scripts/test-concurrency.sh`, `npm run build`, and the relevant Playwright tests after behavior changes.
Tests must use PostgreSQL/PostGIS database survival_test, never SQLite or survival.
Keep OSM provenance separate from fictional game state. Imports must never replenish stock. All simulation actions lock world then player then location inside a transaction. Quantities and position are server authoritative.
Use Spanish UI text. Never add actual establishment names without authentic OSM input.
