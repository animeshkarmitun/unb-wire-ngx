# Scale & Performance Optimization Research — 1,000 Concurrent Portal Users + ~50 Concurrent Admins

> **Status:** research only — no code/infra changes made (per request).
> **Scope:** app-level, query-level, and DB-level optimizations for UNB Wire under the load model:
> ~1,000 concurrent client-facing (portal/API) users, ~50 concurrent admin/editorial users on the Livewire panel.
> **Date:** 2026-10-11
> **Alignment:** `app-data/v1-non-functional-requirements.md` §0 load model, §2 R/W split, §5 SLOs (feed API p95 < 200ms, search p95 < 300ms, publish→feed ≤ 60s), breaking-news multiplier 10–50×.

---

## 1. Load model & capacity math

| Metric | Value | Basis |
|---|---|---|
| Concurrent portal users | ~1,000 | user scenario (breaking-event surge) |
| Concurrent admin/editorial staff | ~50 | user scenario (within NFR's 60–120 band) |
| Expected portal RPS at surge | ~200–500 req/s | 1,000 users × page/API interactions every 2–5s |
| Feed cache hit ratio target | > 95% | 60s Redis + CDN edge TTL; guest feed = single shared key |
| DB connections without pooling (worst case) | FPM workers + Horizon (10) + scheduler + Reverb | every PHP process holds a PDO connection |
| Default PG `max_connections` | 100 | PostgreSQL default |
| **Risk** | **connection exhaustion at ~60–80 FPM workers** | plain math above — pooler required |

Key character (NFR §0): **read : write ≈ 100 : 1**, write bursts (field uploads, breaking publish).
So the strategy is: **absorb reads in Redis/CDN/Meilisearch, keep primary for writes, multiplex DB connections, and protect the hot path from append-heavy tables.**

---

## 2. Current state — what is already good (keep)

| Area | Evidence |
|---|---|
| Feed cached 60s in Redis per client+URL + `Cache-Control: public, max-age=60` (CDN-ready) | `app/Http/Controllers/Api/PortalController.php:42`, `ClientFeedController.php:61` |
| Keyset (cursor) pagination on client feed — `(published_at, public_id)` tiebreaker, no OFFSET scans | `ClientFeedController.php:50–74` |
| Good story indexes: `(status, published_at)`, `(language, status)`, `(category_id, status)` + partial `stories_published_feed_index (published_at DESC) WHERE status='published'` | `database/migrations/2026_08_27_000021_create_stories_table.php:44–56` |
| Read/write connection split with `sticky => true` (post-write reads stay on primary) | `config/database.php:59–69` |
| Meilisearch offloads portal search from DB — browser-direct with tenant tokens, single index + filters (recommended multi-tenant pattern) | `app/Services/Search/TenantTokenIssuer.php`, Meilisearch docs |
| Rate limiting per endpoint, Redis-backed | `config/rate-limiting.php`, `routes/api.php` |
| Async-first: Horizon queues, `index_outbox` (never index from request path), derivatives/fanout as jobs | NFR §7 house rule is implemented |
| Dashboard KPIs cached 60s | `app/Services/DashboardService.php:35` |
| Entitlement resolution cached 300s | `app/Services/Search/TenantTokenIssuer.php:21` |
| No `wire:poll` polling loops in admin views | grep verified |
| Admin list queries eager-load (`with([...])`) — no N+1 on lists | `app/Repositories/StoryRepository.php:21–27` |
| Deliveries: idempotency unique key, `(client_id, created_at)`, partial failed index | `2026_08_27_000036_create_deliveries_table.php` |

**Verdict:** the architecture is fundamentally right for 1,000 concurrent readers. The gaps are runtime/deployment-level (sessions, pooling, FPM/OPcache, CDN) plus a handful of query-level and invalidation issues below.

---

## 3. Gaps & risks (ranked)

### P0 — will break or visibly degrade at 1,000 concurrent

| # | Gap | Evidence | Impact |
|---|---|---|---|
| P0-1 | **`SESSION_DRIVER=database`** — every request does session read + write on Postgres | `.env.example:32` | 1,000 users → thousands of tiny UPDATEs/sec on `sessions` table; contention, autovacuum pressure, replica-lag on a table *every* request touches. Also inflates DB connection hold time. |
| P0-2 | **No connection pooler (PgBouncer)** | no config anywhere in repo | FPM workers (each holds a PDO conn) + Horizon 10 + scheduler + Reverb exceed PG `max_connections=100` at ~60–80 workers; 503s / `FATAL: too many connections` at surge. |
| P0-3 | **No PHP-FPM / OPcache / deploy optimization documented** (repo dev-only: `artisan serve`) | `composer.json` `dev` script | Without OPcache + `config:cache`/`route:cache`, every request recompiles ~2–3k PHP files → 50–200ms overhead/request → FPM worker pool saturates well below target RPS. |
| P0-4 | **No CDN in front of the API origin** despite NFR §5 ("surges mostly hit CDN/Redis") and NFR §2 ("cacheable 30–60s, Redis + CDN edge") | headers already set correctly (`max-age=60`) | 1,000 users all hit origin Laravel on cache misses/storms; a CDN edge would cut origin RPS by 10–100×. |
| P0-5 | **Feed cache invalidation is effectively broken** — `invalidatePortalFeedCache()` forgets only `feed:v1:{id}:md5(base-url)` with **no query string**, but real keys hash `fullUrl()` incl. `?since=…&limit=…`; and it never clears `portal:feed:*` keys at all | `app/Console/Scheduling/LiftEmbargoedStories.php:51–70` vs `ClientFeedController.php:60`, `PortalController.php:40` | Publish/kill/embargo-lift leaves **stale cached feeds for up to 60s TTL** on most variants → violates publish→feed ≤ 60s SLO at the boundary; kills must propagate fast (client trust). |
| P0-6 | **Cache stampede on hot feed keys** — plain `Cache::remember`, no lock/stale-while-revalidate | `PortalController.php:42`, `ClientFeedController.php:61` | On a shared key (guest feed) expiry at surge, hundreds of concurrent misses regenerate the same payload → DB burst exactly when traffic is highest. |

### P1 — degrades as data/traffic grows

| # | Gap | Evidence | Impact |
|---|---|---|---|
| P1-1 | **`ILIKE '%term%'` / `LIKE '%term%'` SQL search** in portal feed + admin list + `heroStory` (scans `body_text`) | `PortalController.php:66–68`, `StoryRepository.php:378–383, 204–210` | Unindexable seq scan on `stories` for every search keystroke; grows linearly with table; NFR §1 says search must *never* be SQL LIKE. |
| P1-2 | **No index supporting admin list sort** — `filteredList` filters by `language`+`status`+`category` then orders by `updated_at DESC`, but no `(language, status, updated_at)` / `(status, updated_at)` index exists | `StoryRepository.php:21–27` vs stories migration | Seq scan + sort on every admin list render; 50 admins × every click (Livewire re-render) — gets worse as stories accumulate. |
| P1-3 | **`deliveries` growth blows past DEC-011's "~5M rows" partitioning trigger in ~2 months** | NFR §0: 300–600 stories/day × 50–200 clients × channels | 600 × 100 clients × 1.5 channels ≈ **90k rows/day → 5M in ~55 days, ~33M/yr**. Distribution-log views + autovacuum on this table will degrade first; DEC-011 must be revisited *for this table specifically*. |
| P1-4 | **Horizon: all 6 queues on one supervisor** (`default, outbox, fanout, derivatives, notifications, billing`), prod `maxProcesses=10`, `balance=auto` | `config/horizon.php:199–229` | A breaking-news fan-out burst (NFR: 10–50×) starves `notifications`/`outbox` behind long FTP pushes; Meilisearch index lag alarm (>30s) trips. |
| P1-5 | **`predis` (pure PHP) as client default in `.env.example`** vs `phpredis` extension | `.env.example:45` vs `config/database.php:103` | predis adds per-request overhead (userland serialization); phpredis ext is measurably faster and holds less CPU per Redis op at high RPS. |
| P1-6 | **Feed payload embeds full `body_html`** in list cache entries | `PortalController.php:78` | Cache entries 50–200KB each × clients × filter variants → Redis memory + serialization CPU per hit; list API should return briefs only (body via `show` endpoint, which is separately cacheable per-story). |
| P1-7 | **`audit_logs` / `deliveries` / `downloads` are OFFSET-paginated** with 7-year retention | `AuditLogRepository.php:41,73`, `DeliveryRepository.php:36` | Deep pages get slower linearly (`OFFSET 100000` scans+discards); NFR §2 already says "paginate, archive old partitions". |

### P2 — worthwhile, not urgent

| # | Gap | Note |
|---|---|---|
| P2-1 | `statusCounts()` runs 1 GROUP BY over whole language partition on each admin list render | `StoryRepository.php:86–100` — at 50 admins fine; wrap in 60s `Cache::remember` when it appears in hot components |
| P2-2 | Livewire pages don't use `lazy` placeholder loading for heavy sub-components | Livewire docs — `<livewire:x lazy>` defers offscreen components' queries |
| P2-3 | Meilisearch is single-node (NFR accepts this for v1–v2) | Add read replica for surge HA when traffic materializes; give it dedicated cores + RAM ≥ 2× index size |
| P2-4 | No `pg_stat_statements` / slow-query log / Horizon wait alarms in monitoring plan | Observability prerequisite before tuning (see §7) |
| P2-5 | `synchronous_commit`, `wal_compression`, checkpoint tuning untouched | Default PG config wastes WAL bandwidth on bursty fan-out writes |

---

## 4. Recommendations by level

### 4.1 Edge / HTTP layer

1. **Put the API + portal behind a CDN** (NFR already mandates). Feed responses already send `Cache-Control: public, max-age=60` — the edge can serve them directly for 60s. Config guidance: edge TTL 60s matching origin, bypass for `/api/v1/portal/login|forgot-password|reset-password|search-token` (authenticated), purge/feed-bust on publish via CDN API if < 60s propagation is ever required.
2. **Enable gzip/brotli + HTTP/2 + keep-alive at nginx/CDN** — JSON feed payloads compress 70–80%.
3. **Tune nginx fastcgi buffers** (`fastcgi_buffers 16 16k`, `fastcgi_read_timeout 40s`) so large Livewire payloads don't spill to disk.

### 4.2 PHP runtime layer

1. **OPcache (production):** `opcache.enable=1`, `opcache.memory_consumption≥256`, `opcache.interned_strings_buffer=16–64`, `opcache.max_accelerated_files=20000+`, **`opcache.validate_timestamps=0`** + reload php-fpm on deploy (atomic symlink deploys).
2. **Laravel deploy optimization:** `php artisan optimize` (config+routes+views+events cached), `composer install --no-dev` (autoloader classmap already optimized in composer.json).
3. **PHP-FPM pool sizing (formula, then measure):**
   - `pm = static`; `pm.max_children = available RAM / measured settled worker RSS`. Laravel workers settle ~50–80MB bare, 120–180MB for a full modular monolith — **measure** with `ps -ylC php-fpm --sort :rss`.
   - Worked example (8 vCPU / 16GB, Redis + nginx local): reserved ~2GB → ~14GB for FPM → at 120MB/worker → **~115 workers**, but cap at `2 × vCPU` for CPU sanity (16) → the surplus headroom is exactly why **PgBouncer transaction pooling** (not raw connections) matters.
   - `pm.max_requests = 500–1000` (recycle leaking workers).
4. **Switch Redis client to `phpredis`** extension (`REDIS_CLIENT=phpredis`) — consistent with `config/database.php` default; faster than predis at high RPS.
5. **Sessions → Redis** (see 4.3) — removes the DB from the per-request path entirely.

### 4.3 Application level (Laravel)

1. **`SESSION_DRIVER=redis`** with a **dedicated Redis connection + prefix** (`config/database.php` redis `session` connection, `config/session.php connection`) — admin *and* portal users move off Postgres per-request I/O. Keep `SESSION_SECURE_COOKIE=true` in prod.
2. **Stampede-proof the feed caches:** replace `Cache::remember($key, 60, …)` with **`Cache::flexible($key, [30, 120], …)`** (stale-while-revalidate, Laravel 12) or a `Cache::lock()->block(2, …)` single-flight wrapper. One regenerator; others serve stale. This is the cheapest resilience win for the shared guest key.
3. **Fix invalidation — versioned keys instead of URL-hash bookkeeping:** keep a Redis counter `feed:epoch`; include it in every feed cache key (`feed:v1:{epoch}:{clientId}:{hash}`); on publish/kill/embargo-lift `INCR feed:epoch` (O(1), no SCAN loops, covers **all** variants incl. query strings, both `portal:feed:*` and `feed:v1:*`). This satisfies the NFR's "tag-based invalidation" intent without per-key enumeration. (Redis store also supports `Cache::tags()` — but epoch-counter is simpler and immune to key-format drift like P0-5.)
4. **Route search away from SQL:** portal feed `search` param and admin `filteredList` search should call Meilisearch (already deployed) or, as a DB-side fallback for admin-only title search, add a **`pg_trgm` GIN index** on `stories.headline` (`CREATE EXTENSION pg_trgm; CREATE INDEX stories_headline_trgm ON stories USING gin (headline gin_trgm_ops);`) — makes `ILIKE '%x%'` index-assisted. Remove `body_text LIKE` from `heroStory` (unbounded).
5. **Slim the feed list payload:** drop `body_html` from list responses (brief only) — cut Redis entry size ~5–10×; story detail endpoint stays per-public-id cacheable (immutable after publish → `Cache::flexible` long TTL, NFR §2 "aggressive cache").
6. **Split Horizon supervisors** so bursts don't starve latency-sensitive queues:
   - `supervisor-fanout`: queues `fanout` (FTP pushes, retries), `maxProcesses` high (e.g. 10–16), timeout 300.
   - `supervisor-quick`: `outbox`, `notifications`, `default`, `billing` — small, fast jobs, `balance=auto`.
   - Derivatives can stay on fanout-pool or its own.
   - This matches NFR §7 "workers are separate processes … each tier scales independently."
7. **Livewire (50 admins):** keep components thin (already the rule); adopt `<livewire:… lazy>` for heavy widgets; prefer `wire:model.defer` for filter forms; use `#[Computed]` properties to avoid re-querying; keep `paginate(15)` page sizes (already); don't store Eloquent collections as public properties.
8. **Read-replica routing discipline:** feed/guest reads ride `DB_READ_HOST` (already); monitor **replica lag** — publish→feed-visible ≤ 60s SLO gives a lag budget of a few seconds max; alert at > 5s.

### 4.4 Query level

1. **Add admin-list indexes** (one migration, follow `docs/knowledge-inventory/data-model.md` conventions):
   ```sql
   CREATE INDEX stories_lang_status_updated_index ON stories (language, status, updated_at DESC);
   CREATE INDEX stories_status_updated_index      ON stories (status, updated_at DESC); -- 'all' status case
   ```
2. **Keyset (cursor) pagination for append-heavy log tables** (`audit_logs`, `deliveries`, `downloads`) — they already have `(created_at)` leading composites; switch repos to `cursorPaginate()` on `id`/`created_at` for deep-page O(1).
3. **`select()` only needed columns** on admin list + feed queries (stories `body_html/body_text` excluded from list selects — big TOAST win).
4. **`statusCounts()`** → 60s cache or a `(language, status)` covering count (index `(language, status)` already exists — query is index-only once counts cached).
5. **Avoid per-row `whereHas` in loops** (none found — keep it that way; lazy-loading guard `Model::preventLazyLoading` already on in dev).

### 4.5 Database level (PostgreSQL 16)

1. **PgBouncer in front of primary (transaction mode) + replica:**
   ```ini
   [pgbouncer]
   pool_mode = transaction
   max_client_conn = 2000        ; FPM workers + Horizon + CLI
   default_pool_size = 25        ; ≈ (DB vCPU × 2) + 1 — measure, don't guess
   reserve_pool_size = 5
   server_idle_timeout = 300
   query_wait_timeout = 10
   max_prepared_statements = 200 ; 1.21+ protocol-level prepare tracking
   ```
   - **PHP/PDO prepared statements caveat:** PgBouncer 1.21+ tracks protocol-level prepares, **but PHP/PDO only cooperates on PHP 8.4 + libpq 17**. For current stacks add to the `pgsql` connection in `config/database.php`: `'options' => [PDO::ATTR_EMULATE_PREPARES => true]` (community-standard Laravel workaround), **or** run session mode for the admin pool (only ~50 admins).
   - Transaction mode breaks session features: no `SET`/`LISTEN`/session advisory locks/temp tables across transactions — verified codebase uses none of these across transaction boundaries (search_path is default `public`, no `PREPARE` SQL).
   - **Migrations/DDL and `artisan` maintenance bypass the pooler** (direct connection) — standard rule.
   - Split pools: write pool → primary; read pool → replica; keep `sticky => true` (sticky pins the request to primary after a write — compatible).
2. **PG16 OLTP GUCs (16GB dedicated-DB-server worked example, then measure):**
   ```ini
   shared_buffers = 4GB            # 25% RAM
   effective_cache_size = 12GB     # 75% RAM (planner hint)
   work_mem = 32MB                 # RAM*0.25/max_connections, conservative for OLTP
   maintenance_work_mem = 1GB
   autovacuum_work_mem = 512MB
   wal_buffers = 16MB
   max_connections = 120           # behind PgBouncer (25–30 active) this is generous
   random_page_cost = 1.1          # SSD
   effective_io_concurrency = 200
   default_statistics_target = 100
   wal_compression = on
   max_wal_size = 4GB              # bursty fan-out writes
   checkpoint_completion_target = 0.9
   jit = off                       # short OLTP queries don't pay plan cost
   huge_pages = try
   idle_in_transaction_session_timeout = 60s
   log_min_duration_statement = 250ms   # slow-query feed for §7
   shared_preload_libraries = 'pg_stat_statements'
   ```
3. **Per-table autovacuum for append-heavy, fast-turnover tables** (deliveries, downloads, notifications, audit_logs, story_events, index_outbox):
   ```sql
   ALTER TABLE deliveries SET (autovacuum_vacuum_scale_factor = 0.02, autovacuum_analyze_scale_factor = 0.01);
   ```
   Deliveries rows churn (queued→sent→delivered UPDATEs) + insert storms on publish → default 0.2 scale factor vacuums too late.
4. **Revisit DEC-011 for `deliveries` specifically:** at NFR volumes it crosses 5M rows in ~55 days. Plan **monthly RANGE partitions on `created_at`** (per `v1-database-design.md` convention) + the NFR §4 archival sweep (delivered > 12 months → archive schema), which also keeps distribution-log UI fast. `audit_logs`/`downloads` follow the same pattern on the same trigger.
5. **Replica:** hot feed reads already routed there; keep `hot_standby_feedback = on`, monitor `replay_lag` (alert > 5s — the 60s publish→visible SLO depends on it).
6. **Checklist against conventions already enforced:** `timestamptz` everywhere, CHECK enums, composite + partial indexes present — schema parity gate (`php scripts/schema-parity-check.php`) keeps this honest.

### 4.6 Search layer (Meilisearch)

- Current design (single index + tenant-token filters) **matches Meilisearch's recommended multi-tenant pattern** — do not create per-client indexes.
- Capacity: single node handles low-millions of docs; for 1,000 concurrent searchers at surge, size the box **cores ≥ 4, RAM ≥ 2× index size**, keep `searchableAttributes` minimal (headline, brief, en_tags — not full body), `filterableAttributes` = entitlement/category/language fields only.
- Add a read replica (LB'd) before election-night-type events; portal gracefully degrades to chronological feed (NFR §5) if search dies.

---

## 5. Recommended rollout order (cheapest → deepest)

| Phase | Items | Effort | Expected effect |
|---|---|---|---|
| 1 — config only | Redis sessions, phpredis, OPcache + FPM tuning, `artisan optimize` on deploy, CDN in front | small | removes DB from request path; 2–5× PHP throughput; origin RPS ÷ 10+ |
| 2 — small code fixes | epoch-keyed cache invalidation (P0-5), `Cache::flexible` stampede guard (P0-6), admin-list indexes (P1-2), feed payload slim (P1-6) | small | SLO-safe publish visibility; stable p95 at surge |
| 3 — infra | PgBouncer transaction pools (write+read), PG16 GUCs, autovacuum per-table | medium | connection headroom; consistent latency under 500 RPS |
| 4 — structural | Horizon supervisor split (P1-4), deliveries partitioning + archival sweep (P1-3), keyset pagination on logs (P1-7), search routing (P1-1) | larger | long-term growth headroom |

---

## 6. Load-test acceptance plan (before/after each phase)

- Tool: k6 (or Playwright API-based, already in repo).
- Scenarios (all against a seeded prod-like DB, caches cold then warm):
  1. **Feed soak:** 1,000 VUs, `/api/v1/portal/feed` + `/api/v1/portal/story/{id}`, think-time 2–5s → assert **p95 < 200ms**, error rate < 0.1% (NFR §5).
  2. **Guest storm:** 1,000 VUs on the same guest key with cache forced-expired → assert single-flight regeneration (no DB query burst) — validates P0-6 fix.
  3. **Admin concurrent:** 50 VUs browsing lists / opening stories / saving notes → assert p95 < 500ms, no DB errors.
  4. **Breaking-news spike:** 10× feed traffic + 1 publish with fan-out to 100 clients × 2 channels → assert Meilisearch outbox lag < 30s, deliveries insert throughput, no `notifications` starvation (validates 4.3.6 / P1-4).
  5. **Replica lag check** during spike 4 → alert threshold 5s.
- Gate: golden signals per NFR (feed API latency, publish→distribute lag, upload failure) observed via Horizon + `pg_stat_statements` + slow log.

---

## 7. Observability prerequisites

- `pg_stat_statements` + `log_min_duration_statement=250ms` — find real top queries before/after tuning (don't tune blind).
- Horizon wait alarms (already `redis:default => 60s` threshold — extend per-queue).
- Redis: monitor `evicted_keys`, `mem_fragmentation`, queue depth per supervisor. **Eviction policy caution:** queue payloads and cache keys share one Redis instance (DBs 0/1) — `maxmemory-policy volatile-lru` protects queue keys (no TTL ⇒ never evicted); `allkeys-lru` would silently drop queued jobs under memory pressure. At scale, prefer a second small Redis for cache, `noeviction` for queue.
- PG: `pg_stat_activity` conn counts (validate PgBouncer ceiling), `pg_stat_user_tables` dead tuples on deliveries, replication `replay_lag`.

---

## 8. Sources

- PostgreSQL 16 docs — resource consumption (shared_buffers 25% / ≤40%, work_mem, autovacuum): postgresql.org/docs/16/runtime-config-resource.html
- PostgreSQL production/OLTP tuning patterns (effective_cache_size 75%, random_page_cost 1.1 SSD, max_connections behind pooler): oneuptime.com OLTP tuning guide; EDB memory-tuning guide
- PgBouncer feature matrix & FAQ (transaction-mode limitations; 1.21+ `max_prepared_statements`; PHP/PDO requires PHP 8.4 + libpq 17, else emulate prepares): pgbouncer.org/features.html, pgbouncer.org/faq.html
- Laravel + PgBouncer transaction pooling practice (what breaks, emulated prepares, "app traffic pooled, DDL direct"): dev.to "When Transaction Pooling Starts Making Sense in Laravel"; Heroku PgBouncer best practices
- Laravel cache docs — `Cache::flexible` stale-while-revalidate, atomic locks, Redis store tag support: laravel.com/docs/12.x cache
- PHP-FPM/OPcache production tuning (pm sizing by measured RSS, `pm.max_requests`, `validate_timestamps=0`, worker memory growth): dev.to php-fpm tuning cheat sheet; DCHost Laravel production tune-up; Forge docs
- Meilisearch multi-tenancy (single index + tenant tokens, RAM sizing, high-concurrency search behavior): meilisearch.com/blog/multi-tenancy, meilisearch SaaS solutions page
- Livewire performance (lazy components, defer, computed props, cursorPaginate, payload discipline): livewire docs/discussions
- Internal: NFR load model/SLOs (`app-data/v1-non-functional-requirements.md` §0–§7), `docs/knowledge-inventory/decisions.md` (DEC-011 partitioning), architecture inventory.

---

*This document is research output only. Implementation of any item above must follow `docs/workflow.md` (task file → plan → live smoke → review) and the quality gates in AGENTS.md §10.*
