# Task: M15-SCALE-001 — Scale hardening P0/P1: feed-cache epoch + stampede guard, Redis sessions, admin-list + trgm indexes, autovacuum, Horizon split

**Status:** ✅ Completed
**Dependencies:** M14-COV-* (test harness), M13-PERF-001
**Parent ADR:** `docs/research/scale-performance-optimization-research.md` (research basis), DEC-017 (new — feed cache epoch versioning)

---

## 1. Contract (What)

- **Inputs:** No new request parameters. Internal: `App\Support\FeedCache` (epoch counter + key builders).
- **Outputs:**
  - `/api/v1/portal/feed` and `/api/v1/feed` responses unchanged in shape; cache freshness contract changes: publish/kill/archive/embargo-lift/published-re-edit make new content visible on the **next request** (was: up to 60s stale, invalidation effectively broken — research P0-5).
  - Caching uses `Cache::flexible([30, 120])` stale-while-revalidate (stampede guard — P0-6).
  - Sessions move `database` → `redis` (dedicated `session` Redis connection, DB 2, own prefix) — P0-1.
  - New indexes: `stories (language, status, updated_at)`, `(status, updated_at)` (P1-2); `pg_trgm` GIN on `stories.headline` + `stories.brief` (P1-1 DB-side).
  - Autovacuum storage params on append-heavy tables (P1-3 mitigation; partitioning itself deferred).
  - Horizon: `supervisor-quick` (default/outbox/notifications/billing) + `supervisor-fanout` (fanout/derivatives, longer timeout) — P1-4.
- **Authorization:** Unchanged (resolve.client / portal.session / client.api middleware already in place).
- **Out of scope (explicitly deferred):** feed payload slim (P1-6 — portal uses `body_html` from feed inline, contract change needs own task), PgBouncer/PG-GUC/CDN (infra deploy), `deliveries` monthly partitioning (DEC-011 revisit — separate schema task), admin search → Meilisearch routing (separate feature task).

---

## 2. Logic (How)

1. **FeedCache (new `app/Support/FeedCache.php`):** `epoch()` reads Redis counter `feed:epoch` (default 0); `bump()` = `Cache::increment`; `portalKey()/clientKey()` embed `{epoch}` in cache keys → any bump makes every feed cache entry unreachable instantly (O(1), no SCAN, covers all query-string variants).
2. **Controllers:** keys via `FeedCache::*Key()`; `Cache::remember(60)` → `Cache::flexible([30, 120])`.
3. **Bump sites (client-visible state changes only):**
   - `StoryService::transition` — after transaction, when `to` ∈ {published, killed, archived} (replaces per-transition `invalidatePortalFeedCache()` call that ran for every transition incl. drafts).
   - `StoryService::updateDraft` — when story is `published` (re-edit after publish).
   - `LiftEmbargoedStories::handle` — after any lifts; `SweepArchivedStories::handle` — after any archives. Both drop the old broken key-forget helper.
4. **Migrations (3, pgsql-guarded where needed):** admin-list indexes; `CREATE EXTENSION pg_trgm` + 2 GIN indexes; `ALTER TABLE … SET (autovacuum_vacuum_scale_factor 0.02, autovacuum_analyze_scale_factor 0.01)` on deliveries, downloads, notifications, audit_logs, story_events, index_outbox.
5. **Config:** `database.php` redis `session` connection; `.env`/`.env.example` `SESSION_DRIVER=redis`, `SESSION_CONNECTION=session`, `REDIS_SESSION_DB=2`; `.env.example` `REDIS_CLIENT=phpredis` (local keeps `predis` — phpredis ext not installed on this box); `horizon.php` supervisor split.

---

## 3. Context (Where)

- **Files to create:** `app/Support/FeedCache.php`, 3 migrations under `database/migrations/2026_10_11_*`, `tests/Feature/Api/FeedCacheInvalidationTest.php`, this task file.
- **Files to modify:** `app/Http/Controllers/Api/PortalController.php`, `app/Http/Controllers/Api/ClientFeedController.php`, `app/Services/StoryService.php`, `app/Console/Scheduling/LiftEmbargoedStories.php`, `app/Console/Scheduling/SweepArchivedStories.php`, `config/database.php`, `config/horizon.php`, `.env`, `.env.example`, `tests/Feature/SchedulerAndMiddlewareTest.php`, `tests/e2e/helpers/seed-data.php`, `docs/tasks/README.md`, `docs/knowledge-inventory/{decisions,architecture,data-model}.md`, `AGENTS.md` (§7 cache-invalidation wording).
- **Reference:** NFR §2 (feed cacheable 30–60s), §5 (publish→feed ≤ 60s), research doc §4.3–4.5.

---

## 4. Prompt (For the Coding AI)

Implement §2 exactly. Epoch bump must be outside DB transactions (cache op, not rollback-able, harmless if bumped early). Keep `feed:v1:` / `portal:feed:` prefixes stable, insert epoch segment: `feed:v1:{epoch}:{clientId}:{md5}` / `portal:feed:{epoch}:{clientId}:{md5}`. Do not change response shapes, HTTP headers (`Cache-Control: public, max-age=60` stays), or query logic inside the cache closures. e2e helper `seed-data.php` replaces its 4 per-key `Cache::forget` lines with one `FeedCache::bump()`.

---

## 5. Test Criteria

- [x] New `FeedCacheInvalidationTest`: warm feed → `StoryService::transition(publish)` → next feed request shows the story (no 60s stale wait); kill → next request reflects tombstone/absence (portal feed: gone).
- [x] Updated `SchedulerAndMiddlewareTest`: embargo lift bumps epoch; old-epoch key unreachable.
- [x] Existing feed entitlement/tombstone tests stay green (cache isolation per client preserved).
- [x] `php artisan migrate:fresh --seed` clean; `php scripts/schema-parity-check.php` PASSED.
- [x] `php -l` clean on all changed PHP files; `php artisan test` green.
- [x] Live smoke per `docs/workflow/live-test-runbook.md` recorded below.

---

## 6. Completion Notes

- **Shipped:** `App\Support\FeedCache` (epoch counter + key builders, DEC-017); epoch-versioned + `Cache::flexible([30,120])` feed caching in `PortalController`/`ClientFeedController`; epoch bumps on publish/kill/archive transition (post-transaction), published re-edit, embargo lift, archive sweep (replaces broken `invalidatePortalFeedCache`); sessions → dedicated `session` Redis connection (DB 2); `.env.example` `phpredis` default; admin-list indexes `(language,status,updated_at)` + `(status,updated_at)`; `pg_trgm` + GIN on headline/brief; per-table autovacuum (0.02/0.01) on 6 append-heavy tables; Horizon `supervisor-quick`/`supervisor-fanout` split.
- **Tests:** `php artisan test` — **901 passed (2896 assertions), 0 failures** (incl. new `FeedCacheInvalidationTest` ×4 + rewritten epoch invalidation test). `php artisan migrate:fresh --seed` clean. `php scripts/schema-parity-check.php` — all PASSED. `php -l` clean on all 14 changed PHP files. `npm run build` clean (3.17s).
- **Live Smoke** (runbook: `optimize:clear` first; dev server on :8000):
  - `GET /api/v1/portal/feed` → 200, `Cache-Control: max-age=60, public` ✓, guest feed warmed at epoch 0
  - Publish via `StoryService::transition` → epoch 0→1 → **same URL next request contains the new story immediately** (old bug: ≤60s stale) ✓
  - Kill → epoch 1→2 → **same URL next request no longer contains it** ✓
  - Session key present in Redis DB 2 (`session` connection) after admin page visit ✓
  - Targeted Playwright (`ui-admin-auth`, `news-list-faithful`, `portal-auth`, `web-auth-rbac`, `auth-edges`): **49 passed, 6 failed — all 6 proven pre-existing** (stash-rerun on pre-change code reproduced identical failures): `service.blade.php` has no `h1/h2/.page-title/.breadcrumb` (2), tinker seed uses `2>/dev/null` which Windows cmd cannot spawn (2), `auth-edges` logout-locator + Tus 200-vs-401/422 (2, part of pending `M14-COV-026`). Login/auth/session flows all green.
  - `monitor:outbox-lag` reports LAG inflated by a pre-existing tz quirk (monitor subtracts UTC-interpreted `created_at` from `+06` now → +6h offset); real pending lag was ~4 min (smoke kill op awaiting dev queue worker). Flagged for a future fix — out of scope here.
- **Deferred (own tasks):** feed payload slim (portal uses `body_html` inline — API contract), PgBouncer + PG16 GUCs + CDN (infra deploy, configs documented in research doc §4), `deliveries` monthly partitioning (DEC-011 revisit — crosses 5M rows in ~55 days at NFR volume), admin search → Meilisearch routing, `monitor:outbox-lag` tz fix.
- **Review:** reviewer subagent not available in this session — all 4 CI gates (test/build/parity/playwright-targeted) executed locally and recorded above; PR CI will re-run them on the branch.
