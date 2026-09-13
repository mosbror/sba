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

## Production Docker setup on the VPS

Production is separate from local development and uses:

- `docker-compose.prod.yml`
- `docker/backend/Dockerfile.prod`
- `docker/frontend/Dockerfile.prod`
- `.env.production`

The VPS already runs one central Caddy container for HTTP/HTTPS. The SBA production Compose file therefore does not include a Caddy service and does not publish any host ports. The frontend and backend join the existing external Docker network named `caddy`; MySQL and Redis stay only on the private internal network.

### Production traffic flow

```text
Internet
  -> existing VPS Caddy :80/:443
      -> sba-app.mosnet.cyou -> sba-app:3000 -> Nuxt/Nitro
      -> sba-api.mosnet.cyou -> sba-api:8080 -> nginx -> PHP-FPM -> Laravel public/index.php

frontend -> internal -> backend:8080/api for server-side Nuxt calls
backend  -> internal -> mysql:3306
backend  -> internal -> redis:6379
browser  -> https://sba-api.mosnet.cyou/api for public API calls
```

Network layout:

```text
existing VPS Caddy
       |
    caddy
  /       \
sba-app  sba-api
:3000    :8080
  \       /
   internal
   /     \
mysql   redis
```

### Central VPS Caddy config

Do not put Caddy in the SBA Compose stack. Add these blocks to the existing VPS Caddyfile managed outside this project:

```caddyfile
sba-app.mosnet.cyou {
    reverse_proxy sba-app:3000
}

sba-api.mosnet.cyou {
    reverse_proxy sba-api:8080
}
```

The central Caddy container must be attached to the external Docker network named `caddy`.

### Production environment

On the VPS, the deployment directory can be as small as:

```text
/srv/containers/sba.mosnet.cyou/docker-compose.prod.yml
/srv/containers/sba.mosnet.cyou/.env.production
```

The application code lives inside the GHCR images.

Create the production env file:

```bash
cp .env.production.example .env.production
```

Edit `.env.production` and set at minimum:

- `GHCR_OWNER`
- `IMAGE_TAG`, usually `latest` or a release tag like `v1.0.0`
- `APP_KEY`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD`

Production URL defaults are:

```text
APP_URL=https://sba-api.mosnet.cyou
FRONTEND_URL=https://sba-app.mosnet.cyou
NUXT_PUBLIC_API_BASE=https://sba-api.mosnet.cyou/api
```

Generate an `APP_KEY` from the backend image:

```bash
docker run --rm ghcr.io/${GHCR_OWNER}/sba-backend:${IMAGE_TAG:-latest} \
  php artisan key:generate --show
```

Paste the returned value into `.env.production` as `APP_KEY=...`.

Do not commit `.env.production`.

### Build and push images to GHCR

GitHub Actions is not configured here. Releases are expected to be built from Git tags and published to GHCR, for example both `:latest` and `:v1.0.0`.

Manual build/push from the repository root:

```bash
export GHCR_OWNER=OWNER
export IMAGE_TAG=v1.0.0

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

# Optional: also publish latest from the same release.
docker tag ghcr.io/${GHCR_OWNER}/sba-backend:${IMAGE_TAG} ghcr.io/${GHCR_OWNER}/sba-backend:latest
docker tag ghcr.io/${GHCR_OWNER}/sba-frontend:${IMAGE_TAG} ghcr.io/${GHCR_OWNER}/sba-frontend:latest
docker push ghcr.io/${GHCR_OWNER}/sba-backend:latest
docker push ghcr.io/${GHCR_OWNER}/sba-frontend:latest
```

The production Dockerfiles use:

- backend: PHP `8.4`, nginx, PHP-FPM, Composer install with `--no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts`
- frontend: Node `22`, `npm ci`, `npm run build`, then `node .output/server/index.mjs`

### First production deployment

On the VPS:

```bash
sudo mkdir -p /srv/containers/sba.mosnet.cyou
cd /srv/containers/sba.mosnet.cyou

# copy docker-compose.prod.yml and create/edit .env.production here
docker login ghcr.io

# one-time prerequisite if the shared reverse-proxy network does not already exist
docker network create caddy

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

After publishing a new image tag, for example `v1.0.1`, update `IMAGE_TAG` in `.env.production`, then run:

```bash
cd /srv/containers/sba.mosnet.cyou

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
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend frontend mysql redis
```

### Production configuration choices

- Backend uses nginx inside the backend image in front of PHP-FPM and serves Laravel through `public/index.php` on internal port `8080`.
- Frontend uses the prebuilt Nuxt/Nitro server on internal port `3000`.
- The central VPS Caddy handles public HTTP/HTTPS and proxies to Docker aliases `sba-app` and `sba-api`.
- No SBA service publishes host ports.
- MySQL data is stored in persistent volume `mysql_data`.
- Redis data is stored in persistent volume `redis_data` with append-only mode enabled.
- Laravel cache uses Redis: `CACHE_STORE=redis`.
- Laravel sessions use the database: `SESSION_DRIVER=database`, which is simple and persistent across Redis resets.
- Queue is `sync` because no queue worker service is configured and the MVP does not require background jobs.
- Laravel logs go to stderr through `LOG_CHANNEL=stderr`, visible with `docker compose ... logs`.
- Laravel config/view caches are created at backend container startup. Route cache is intentionally not enabled yet because the current API routes include closures.
- Production CORS is restricted with `CORS_ALLOWED_ORIGINS=https://sba-app.mosnet.cyou`.

### Authentication/CORS status

The current app does not yet implement Sanctum or credentialed session-cookie login in the frontend. Current frontend requests do not send credentials.

For the current MVP deployment:

- browser API base is `https://sba-api.mosnet.cyou/api`
- Laravel CORS allows `https://sba-app.mosnet.cyou`
- `CORS_SUPPORTS_CREDENTIALS=false`

If Sanctum/session-cookie authentication is added later, revisit this and configure:

- `SANCTUM_STATEFUL_DOMAINS=sba-app.mosnet.cyou`
- `SESSION_DOMAIN=.mosnet.cyou`
- `SESSION_SECURE_COOKIE=true`
- credentialed frontend fetch requests
- `CORS_SUPPORTS_CREDENTIALS=true`

### Manual production prerequisites

Before exposing the app publicly:

1. DNS: point `sba-app.mosnet.cyou` and `sba-api.mosnet.cyou` to the VPS.
2. Central Caddy: add the two Caddy blocks above and reload the central Caddy container.
3. Docker network: confirm `docker network inspect caddy` works.
4. Firewall: only the central Caddy needs inbound `80/tcp` and `443/tcp`; do not expose MySQL/Redis/frontend/backend ports.
5. GHCR: make sure the VPS can pull the images.
6. Secrets: set strong `APP_KEY`, `DB_PASSWORD`, and `DB_ROOT_PASSWORD` in `.env.production`.
7. Database: run migrations manually after first startup and after releases that include migrations.
8. Seed data: run `db:seed --force` explicitly when installing initial reference data or intentionally updating non-completed reference snapshots.
9. Backups: configure backups for the Docker `mysql_data` volume before real use.
10. Authentication: login/auth UI is still a known remaining hardening item before broad public use.

### Security assumptions

- Production Compose does not publish `3306`, `6379`, `3000`, `8000`, `8080`, or `9000` to the host.
- MySQL and Redis are not attached to the shared `caddy` network.
- Production passwords are read from `.env.production`; no real credentials belong in Git.
- `APP_DEBUG=false` is enforced in Compose.
- HTTPS is handled by the existing central VPS Caddy.
