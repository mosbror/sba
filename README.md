# SBA

App-like web application for quarterly SBA fire-safety inspections for `BRF VIGELSJÖHÖJDEN`.

## Stack

- `backend/`: Laravel 13 / PHP 8.4 JSON API
- `frontend/`: Nuxt 3 / Vue 3 / TypeScript
- `mysql`: MySQL 8.4
- `redis`: Redis 7, included for future cache/session/queue use

## Reference files

- `Checklista SBA.xlsx`
- `Checklista SBA.pdf`

Use these as the source of truth for the initial checklist wording and PDF layout.

## Local development

The repo has been scaffolded with Docker Compose:

```bash
docker compose up -d --wait
```

Local proxy hostnames, expected in `/etc/hosts`:

```text
127.0.0.1 sba-app.local sba-api.local
```

Main URLs:

- App: http://sba-app.local
- API: http://sba-api.local
- Direct frontend dev server: http://localhost:3000
- Direct backend dev server: http://localhost:8000

The backend container clears Laravel caches, runs migrations, and seeds the local reference data on startup.
You can also run migrations manually when needed:

```bash
docker compose exec -T backend php artisan migrate
```

Useful direct commands:

```bash
cd backend && composer run test
cd backend && vendor/bin/pint --format agent
cd frontend && npm run dev
cd frontend && npm run build
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

Known remaining hardening before real public deployment: authentication/login UI, production hosting/secrets, and more visual polish of the generated PDF against the official source layout.
