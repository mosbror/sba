# Agent instructions for SBA

SBA is an app-like web application for quarterly fire-safety inspections (systematiskt brandskyddsarbete) for BRF VIGELSJÖHÖJDEN. It replaces iPad PDF form filling with a responsive web workflow and server-generated archiveable PDF reports that closely resemble the supplied official checklist.

## Current repository state

- Root contains Docker Compose, README, implementation notes, and reference checklist files.
- `backend/`: Laravel 13 / PHP 8.4 API scaffold. Read `backend/AGENTS.md` before editing backend files; Laravel Boost installed project-specific guidance there.
- `frontend/`: Nuxt 3 / Vue 3 / TypeScript scaffold. Active routes live under `frontend/pages/`; duplicate legacy files under `frontend/app/pages/` may exist during cleanup and must stay in sync until removed.
- Reference files: `Checklista SBA.xlsx` and `Checklista SBA.pdf`.

## Product rules

- All visible user-facing application text must be Swedish.
- English is preferred for code, database, API, class, and file naming.
- Primary device: iPad while walking between buildings. Also support phones and desktop browsers.
- Build incrementally; do not turn this into a large enterprise system.
- Do not assume requirements beyond this file and the supplied checklist material.
- Do not add a “Markera allt som OK” feature; every item must be explicitly reviewed.
- Photo capture/upload is out of scope for v1, but the data model must not block future attachments.

## Reference checklist source

Use `Checklista SBA.pdf` as the current source of truth for initial checklist wording and report layout. Extracted structure:

- Header: `CHECKLISTA SBA (systematiskt brandskyddsarbete) Kvartalskontroll`, `Kontrolldatum`, `Signatur`, `BRF VIGELSJÖHÖJDEN`, `Port nr`.
- Columns: `OK`, `ANMÄRKNING`, `KOMMENTAR`, `ÅTG DATUM`.
- Sections: `TRAPPHUS`, `CYKEL/BARNVAGNSFÖRRÅD`, `FÖRRÅDS/TEKNIKGÅNGAR`, `TVÄTTSTUGA 5A`, `UTOMHUS`, `LÄGENHETER`.
- Preserve source spelling unless intentionally corrected with user approval; the PDF contains `Utrymingsskyltar`.

## Required architecture direction

- Use Docker Compose with services planned as `frontend`, `backend`, `mysql`, and `redis`.
- Frontend: Nuxt 3+, TypeScript, Vue 3 Composition API.
- Backend: PHP; Laravel is acceptable and preferred if it speeds up development.
- Database: MySQL 8+.
- Redis must be included but must not be required for basic app functionality unless justified.
- Frontend/backend communicate through a JSON API.
- PDF generation must be server-side; do not rely on browser print dialogs.

## Domain model constraints

- An inspection represents one quarterly control and contains any number of buildings.
- A building uses the same checklist, but items may be marked not applicable.
- Item statuses must include: `unchecked`, `ok`, `remark`, `not_applicable`.
- Inspection statuses must include: `draft`, `in_progress`, `completed`.
- An inspection must be saveable/resumable before completion.
- A building is not complete while any item is `unchecked`; `not_applicable` counts as handled.
- Historical integrity is mandatory: completed/old inspections must not silently change when checklist definitions change. Prefer snapshotting checklist content on inspection creation unless a simpler robust template-version approach is implemented.
- Seed BRF name as `BRF VIGELSJÖHÖJDEN` and seed checklist data from the supplied PDF/XLS only.
- Buildings are configurable in the database. Current seed data is the MVP source (`1A`, `1B`, `3A`, `3B`, `5A`, `5B`); do not block the inspection workflow on a building-admin UI. Add minimal CRUD later only if non-developers must maintain buildings.

## First implementation phases

1. Inspect supplied XLS/PDF, summarize checklist structure, propose schema, navigation, stack choices, PDF approach, and assumptions before coding.
2. Scaffold Docker/backend/frontend, migrations, authentication, seed checklist/building examples.
3. Implement inspection CRUD, building workflow, checklist results, autosave, and resume support.
4. Implement completion validation, history, completed inspection view.
5. Implement PDF generation matching the official checklist layout closely.
6. Polish responsive iPad/iPhone UX, Swedish errors, basic tests, and README.

## UX conventions

- App-like, touch-first interface: large touch targets, clear spacing, sticky navigation where useful, no tiny checkboxes, no horizontal scrolling.
- Building navigation must show current building and progress using text/icons, not colour alone: complete, current/in progress, not started.
- Checklist item UI must show: checklist text, status selector, anmärkning, kommentar, åtgärdsdatum.
- Selecting a status should save immediately; text fields should save on blur or short debounce.
- Show subtle save states in Swedish: `Sparar…`, `Sparad`, `Kunde inte spara`.
- Do not force text input for plain OK items.
- Show clear missing-items feedback before completion instead of silently blocking.

## Authentication and security

- Start with one user but structure `users` for multiple users later.
- Implement username/password login, secure password hashing, authenticated app routes, and logout.
- Use normal Laravel/PHP security if Laravel is chosen: validation, ORM/prepared queries, escaped output, secure cookies/session settings, CSRF where applicable, no production stack traces.
- Keep roles/permissions out of v1 unless the user explicitly asks.

## Verified commands

Root/Docker:
- `docker compose up -d --wait` (backend startup clears Laravel caches, runs migrations, and idempotently seeds reference data for local dev)
- `docker compose exec -T backend php artisan migrate --force`
- `docker compose exec -T backend php artisan db:seed --force`
- `docker compose exec -T backend php artisan migrate:fresh --seed --force`
- `docker compose exec -T backend php artisan test`
- `docker compose exec -T frontend npm run build`
- `docker compose exec -T caddy caddy validate --config /etc/caddy/Caddyfile`

Backend (`backend/`):
- `composer run test`
- `vendor/bin/pint --format agent`
- `php artisan route:list`
- `php artisan migrate`

Frontend (`frontend/`):
- `npm run dev`
- `npm run build`
- `npm run generate`
- `npm run preview`

## Files and folders to avoid

- Do not edit generated/vendor folders once they exist: `vendor/`, `node_modules/`, `.nuxt/`, `.output/`, `dist/`, `build/`, `coverage/`.
- Do not read or print secrets from `.env`, private keys, certificates, or production credentials. Use `.env.example` for documented variable names.

## Definition of done for agents

- Before implementation: inspect available checklist source files and state assumptions.
- For API/frontend work: define the JSON contract before wiring frontend assumptions.
- Before saying done: run the exact relevant tests/build/typecheck commands once they exist, or state clearly that no verified commands exist yet.
- Do not commit or push unless the user explicitly authorizes it.
