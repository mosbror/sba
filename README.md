# SBA

App-like web application for quarterly SBA fire-safety inspections for `BRF VIGELSJÖHÖJDEN`.

## Stack

- `backend/`: Laravel 13 / PHP 8.4 JSON API
- `frontend/`: Nuxt 3 / Vue 3 / TypeScript
- `mysql`: MySQL 8.4
- `redis`: Redis 7
- `caddy`: reverse proxy and production HTTPS

## Reference files

- `Checklista SBA.xlsx`
- `Checklista SBA.pdf`

Use these as the source of truth for the initial checklist wording and PDF layout.

## Local development

The local development workflow is still the root `docker-compose.yml`:

```bash
docker compose up -d --wait
```

Local proxy hostnames, expected in `/etc/hosts`:

```text
127.0.0.1 sba-app.local sba-api.local
```

Main local URLs:

- App: http://sba-app.local
- API: http://sba-api.local
- Direct frontend dev server: http://localhost:3000
- Direct backend dev server: http://localhost:8000

The local backend container clears Laravel caches, runs migrations, and seeds local reference data on startup.
You can also run migrations manually when needed:

```bash
docker compose exec -T backend php artisan migrate
```

Useful local commands:

```bash
cd backend && composer run test
cd backend && vendor/bin/pint --format agent
cd frontend && npm run dev
cd frontend && npm run build
docker compose exec -T caddy caddy validate --config /etc/caddy/Caddyfile
```

## Current status

Implemented MVP flow:

- Laravel/Nuxt/Docker Compose base
- MySQL/Redis services
- domain migrations for buildings, checklist templates/sections/items, inspections, inspection buildings, and item results
- seed data from the supplied XLS/PDF checklist
- API routes for buildings, checklist template, inspection creation, history, resume, item autosave, completion validation, and PDF download
- Nuxt screens for new inspection, previous inspections, building overview, checklist fill-in, completion, and PDF download

Default seeded local user:

- username: `admin`
- password: `password`

Known remaining hardening before real public deployment: authentication/login UI and more visual polish of the generated PDF against the official source layout.

## Production Docker setup

Production is separate from local development and uses:

- `docker-compose.prod.yml`
- `docker/backend/Dockerfile.prod`
- `docker/frontend/Dockerfile.prod`
- `Caddyfile.prod`
- `.env.production`

The production Compose file runs prebuilt GHCR images and does not bind-mount source code.
Only Caddy publishes host ports `80` and `443`.
MySQL, Redis, frontend, and backend are internal Docker services only.

### Production traffic flow

```text
Internet
  -> Caddy :80/:443
      -> /api/*  -> backend:8080 -> nginx -> PHP-FPM -> Laravel public/index.php
      -> /*      -> frontend:3000 -> Nuxt/Nitro

backend -> mysql:3306
backend -> redis:6379
frontend server-side API calls -> backend:8080/api
browser API calls -> same public domain /api
```

This keeps the public app on one domain, for example `https://sba.example.se`, so browser calls and cookies stay same-origin.

### Production environment

Create a real production env file from the example:

```bash
cp .env.production.example .env.production
```

Edit `.env.production` and set at minimum:

- `GHCR_OWNER`
- `IMAGE_TAG`
- `APP_DOMAIN`
- `CADDY_EMAIL`
- `APP_URL`
- `FRONTEND_URL`
- `APP_KEY`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD`

Generate an `APP_KEY` without using a local `.env` value:

```bash
docker run --rm ghcr.io/${GHCR_OWNER}/sba-backend:${IMAGE_TAG:-latest} \
  php artisan key:generate --show
```

Paste the returned value into `.env.production` as `APP_KEY=...`.

Do not commit `.env.production`.

### Build and push images to GHCR

GitHub Actions is not configured yet. For now, build and push manually from the repository root:

```bash
export GHCR_OWNER=OWNER
export IMAGE_TAG=latest

docker login ghcr.io

docker build \
  -f docker/backend/Dockerfile.prod \
  -t ghcr.io/${GHCR_OWNER}/sba-backend:${IMAGE_TAG} \
  .

docker build \
  -f docker/frontend/Dockerfile.prod \
  -t ghcr.io/${GHCR_OWNER}/sba-frontend:${IMAGE_TAG} \
  .

docker push ghcr.io/${GHCR_OWNER}/sba-backend:${IMAGE_TAG}
docker push ghcr.io/${GHCR_OWNER}/sba-frontend:${IMAGE_TAG}
```

The production Dockerfiles use the current project versions:

- backend: PHP `8.4`, Composer install with `--no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts`
- frontend: Node `22`, `npm ci`, `npm run build`, then `node .output/server/index.mjs`

### First production deployment

On the server:

```bash
docker login ghcr.io
cp .env.production.example .env.production
# edit .env.production with real values before continuing

docker compose \
  --env-file .env.production \
  -f docker-compose.prod.yml \
  pull

docker compose \
  --env-file .env.production \
  -f docker-compose.prod.yml \
  up -d

docker compose \
  --env-file .env.production \
  -f docker-compose.prod.yml \
  exec -T backend php artisan migrate --force
```

Seed reference data only when intentionally needed:

```bash
docker compose \
  --env-file .env.production \
  -f docker-compose.prod.yml \
  exec -T backend php artisan db:seed --force
```

Unlike local development, production startup does not run migrations or seeders automatically.

### Future deployments

After pushing a new image tag:

```bash
export IMAGE_TAG=2026-09-13-1
# update IMAGE_TAG in .env.production, or pass it in the shell environment

docker compose \
  --env-file .env.production \
  -f docker-compose.prod.yml \
  pull

docker compose \
  --env-file .env.production \
  -f docker-compose.prod.yml \
  up -d

docker compose \
  --env-file .env.production \
  -f docker-compose.prod.yml \
  exec -T backend php artisan migrate --force
```

Check logs and service state:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ps
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f caddy backend frontend
```

Validate production Caddy config:

```bash
docker run --rm \
  --env-file .env.production \
  -v "$PWD/Caddyfile.prod:/etc/caddy/Caddyfile:ro" \
  caddy:2-alpine \
  caddy validate --config /etc/caddy/Caddyfile
```

### Production configuration choices

- Backend uses nginx inside the backend image in front of PHP-FPM and serves Laravel through `public/index.php` on internal port `8080`.
- Frontend uses the prebuilt Nuxt/Nitro server on internal port `3000`.
- Caddy is the only public service and handles HTTP/HTTPS.
- Caddy stores certificates and ACME state in persistent volumes `caddy_data` and `caddy_config`.
- MySQL data is stored in persistent volume `mysql_data`.
- Redis data is stored in persistent volume `redis_data` with append-only mode enabled.
- Laravel cache uses Redis: `CACHE_STORE=redis`.
- Laravel sessions use the database: `SESSION_DRIVER=database`, which is simple and persistent across Redis resets.
- Queue is `sync` because no queue worker service is configured and the MVP does not require background jobs.
- Laravel logs go to stderr through `LOG_CHANNEL=stderr`, visible with `docker compose ... logs`.
- Laravel config/view caches are created at backend container startup. Route cache is intentionally not enabled yet because the current API routes include closures.

### Manual production prerequisites

Before exposing the app publicly:

1. DNS: point `APP_DOMAIN` to the server running Docker.
2. Firewall: allow inbound `80/tcp` and `443/tcp`; do not expose MySQL/Redis/frontend/backend ports.
3. GHCR: make sure the server can pull the private/public images.
4. Secrets: set strong `APP_KEY`, `DB_PASSWORD`, and `DB_ROOT_PASSWORD` in `.env.production`.
5. Database: run migrations manually after first startup and after releases that include migrations.
6. Seed data: run `db:seed --force` explicitly when installing initial reference data or intentionally updating non-completed reference snapshots.
7. Backups: configure backups for the Docker `mysql_data` volume before real use.
8. Authentication: login/auth UI is still a known remaining hardening item before broad public use.

### Security assumptions

- Production Compose does not publish `3306`, `6379`, `3000`, `8000`, `8080`, or `9000` to the host.
- Production passwords are read from `.env.production`; no real credentials belong in Git.
- `APP_DEBUG=false` is enforced in Compose.
- HTTPS is automatic through Caddy when DNS points to the server and ports 80/443 are reachable.
- Same-domain frontend/API routing uses `/api/*`, reducing CORS/cookie complexity.
