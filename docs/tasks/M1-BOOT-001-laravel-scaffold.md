# Task: M1-BOOT-001 — Laravel Modular Monolith Scaffold + Base Admin Chrome

**Status:** ⏳ Pending
**Dependencies:** M0-TOOL-001, M0-TOOL-002 (docs/contracts synced)
**Parent ADR:** DEC-006 (v1 stack lock), DEC-008 (field apps deferred)

---

## 1. Contract (What)

A runnable Laravel app in the repo root with the locked v1 stack (app-data NFR §16)
and the shared admin chrome from the prototypes, ready for the migration tasks
(DB design §10) and page-conversion tasks (app-data README §11).

- **Stack:** Laravel 12.x, PHP ^8.2, PostgreSQL 16 (default connection), Redis
  (cache + queue), Livewire 3, Alpine, Tailwind CSS, Sanctum (installed, used
  later), Horizon, Reverb, Meilisearch PHP client, S3 filesystem disk config.
- **Auth:** session-based staff login (Breeze, Blade stack) — no Filament, no API
  tokens yet.
- **Admin chrome:** one Blade layout + sidebar/topnav Blade components converted
  from the prototypes (app-data README §4), with design tokens mapped 1:1 into
  `tailwind.config.js` (app-data README §2 — do not eyeball hex values), Google
  Fonts (Fraunces, Inter, Source Serif 4), Lucide icon set.
- **Routes:** `/login` (Breeze), `/admin` dashboard placeholder rendering inside
  the chrome (KPI cards may be static markup for now — real data is a later task).
- **Field apps / portal:** out of scope (DEC-008; portal is a separate Next.js app).

## 2. Logic (How)

1. `composer create-project laravel/laravel` in repo root (existing docs stay).
2. Require: `livewire/livewire:^3`, `laravel/horizon`, `laravel/reverb`,
   `laravel/sanctum`, `meilisearch/meilisearch-php`, `league/flysystem-aws-s3-v3`;
   dev: `laravel/breeze` (Blade stack), keep Pint.
3. `.env.example`: `DB_CONNECTION=pgsql` defaults, `QUEUE_CONNECTION=redis`,
   `CACHE_STORE=redis`, `SESSION_DRIVER=database`, Meilisearch + S3 + Reverb vars.
4. `tailwind.config.js → theme.extend.colors` = app-data README §2 tokens
   (`navy-900`…`purple`, fonts `serif`/`sans`); register Lucide icons.
5. One `layouts/admin.blade.php` + `<x-sidebar>` / `<x-topnav>` components:
   brand strip, navy sidebar (Overview / Newsroom / Field apps / Distribution /
   Settings — field-app links open new tabs), sticky topnav (search with `/` kbd,
   live Dhaka clock via `Intl` `Asia/Dhaka`, bell + user dropdowns).
6. `/admin` placeholder dashboard inside the layout, behind `auth` middleware.
7. Ensure `composer hooks:install` script exists (installs `scripts/pre-commit.sh`).

## 3. Context (Where)

- **Files to Create / Modify:** `composer.json`, `.env.example`,
  `tailwind.config.js`, `resources/views/layouts/admin.blade.php`,
  `resources/views/components/sidebar.blade.php`, `components/topnav.blade.php`,
  `resources/views/admin/dashboard.blade.php`, `routes/web.php`,
  `config/{database,queue,filesystems}.php`
- **Reference Files:** `app-data/README.md` §2 (tokens), §4 (chrome), §11
  (conversion order); `app-data/index.html` (dashboard prototype);
  `app-data/v1-non-functional-requirements.md` §16; `docs/knowledge-inventory/decisions.md` DEC-006/008.

## 4. Prompt (For the Coding AI)

> Scaffold the UNB Wire Laravel app in the repo root per the Contract above.
> Constraints: PostgreSQL is the only DB connection (no MySQL config); no
> Filament; admin auth is session-based (Breeze Blade); Livewire 3 + Alpine +
> Tailwind only for admin JS/CSS. Map the design tokens from `app-data/README.md`
> §2 verbatim into the Tailwind theme; build the shared admin chrome from §4 as
> one layout + sidebar/topnav components (single source, no per-page copies).
> Do NOT create any domain migrations/models — those are separate tasks following
> `app-data/v1-database-design.md` §10. Keep `.env.example` complete for local
> PostgreSQL; for CI (`.github/workflows/ci.yml`) set `DB_CONNECTION=sqlite` in the
> workflow env so the pipeline needs no Postgres service yet (revisit when the
> schema uses PG-only features — partitions, citext — in the migration tasks).
> Verify: `php artisan migrate:fresh --seed` green, `php artisan test` green,
> `/login` and `/admin` render with the prototype chrome at 1440px, Pint clean.

## 5. Test Criteria

- [ ] `composer install` + `npm install && npm run build` succeed from clean clone
- [ ] `php artisan migrate:fresh --seed` runs without errors (PostgreSQL)
- [ ] `php artisan test` passes (default + auth scaffolding tests)
- [ ] `/login` renders; logging in lands on `/admin` with sidebar/topnav chrome
- [ ] Chrome visually matches `app-data/index.html` at 1440px (tokens, fonts, sidebar sections)
- [ ] `tailwind.config.js` contains every token from app-data README §2
- [ ] `./vendor/bin/pint --test` clean; `php -l` clean on all changed files
- [ ] CI workflow green

## 6. Completion Notes

- **Shipped:** —
- **Tests:** —
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
