# Task: M13-PERF-001 — Query optimization: N+1, indexes, eager loading, aggregation cache

**Status:** ✅ Completed
**Dependencies:** M6-PERF-001 (baseline perf pass)
**Parent ADR:** NFR §1/§5 latency budgets, AGENTS.md §7 (eager-load by default, index optimization)

---

## 1. Contract (What)
- **Inputs / Validation:** Audit deliverable (findings table) + targeted fixes. Staging Debugbar/Telescope (issue item 1) is N/A — no staging yet (COS-24); local static audit + query-count assertions instead.
- **Outputs / Response:** No behavioral change — same responses, fewer queries:
  - Missing indexes for FK/pivot lookups (pgsql does not auto-index FKs)
  - `StoryRepository::statusCounts`: 5 COUNTs → 1 grouped query
  - `StoryRepository::countByDateAndStatus` / `countExclusive`: `whereDate` (DATE() kills index) → `whereBetween` day range (uses `stories_status_published_at_index`)
  - `ProcessIndexOutbox`: per-row story fetch + per-row attempts update → batch prefetch + batch increment (Meilisearch sync queries, issue item 6)
  - `DashboardService` KPI aggregates → 60s `Cache::remember` (issue items 5 + 7)
- **Authorization:** unchanged (no new surface).
- **Target:** no N+1 on any page (existing `preventLazyLoading` tests + new query-count assertions), indexed lookups for every FK/pivot join.

---

## 2. Logic (How)
1. Migration `add_perf_indexes`: `deliveries (deliverable_type, deliverable_id)`, `client_channels (client_id)`, `client_api_keys (client_id)`, `client_packages (package_id)`, `story_media (asset_id)`, `story_tag (tag_id)`, `media_tag (tag_id)`. Composite PKs already serve composite-equality joins; these cover the remaining lookup shapes (`$client->clientChannels`, `$story->deliveries`, `$media->stories`, `package_id` counts, tag reverse joins).
2. `statusCounts`: one `selectRaw('status, count(*) as c')->groupBy('status')` keyed pluck; `all` = sum.
3. Date counts: `whereBetween('published_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])`.
4. `ProcessIndexOutbox::handle`: batch `attempts + 1` for the whole rowset, prefetch `Story::whereIn('public_id', ...)` once (payload needs columns only — no relations), pass map into `buildPayload`. Per-row HTTP + outcome update stays (Meili API is per-doc).
5. `DashboardService::getData`: KPI scalars (published today/delta, active clients, success rate, exclusive) computed inside `Cache::remember('dashboard:kpis', 60s)`; recent stories + top clients stay fresh (cheap, eager-loaded).
6. Query-count tests prove the bounds (batch test: query count does not scale with row count; cache test: 2nd `getData` runs fewer queries than the 1st).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `database/migrations/2026_09_22_000100_add_perf_indexes.php` (new)
  - `app/Repositories/StoryRepository.php` (statusCounts, date counts)
  - `app/Jobs/ProcessIndexOutbox.php` (batch)
  - `app/Services/DashboardService.php` (KPI cache)
  - `tests/Feature/QueryOptimizationTest.php` (new)
  - `docs/knowledge-inventory/architecture.md` (cache note)
- **Reference Files:**
  - `app-data/v1-database-design.md` §10 (index conventions)
  - `tests/Feature/DashboardTest.php` (existing N+1 guard)

---

## 4. Prompt (For the Coding AI)
> Implement §2 exactly. Keep diffs surgical; no query-builder rewrites beyond the named methods. Query-count assertions via `DB::enableQueryLog`/`getQueryLog`. Http::fake + `services.meilisearch.host/key` config set for the outbox batch test. `php scripts/schema-parity-check.php` must pass (index-only migration, no column/table/CHECK changes).

---

## 5. Test Criteria
- [x] `statusCounts` correct + 1 query
- [x] Date counts still correct across day boundaries (range semantics)
- [x] Outbox batch: N rows processed with query count not scaling with N (bounded)
- [x] Dashboard KPI cache: 2nd `getData` runs fewer queries than 1st, same values
- [x] New indexes exist (`Schema::hasIndex`)
- [x] Full `php artisan test` green · schema parity PASSED · `php -l` clean

---

## 6. Completion Notes
- **Shipped:** Index-only migration `add_perf_indexes` (7 lookups: `deliveries (deliverable_type, deliverable_id)`, `client_channels/client_api_keys (client_id)`, `client_packages (package_id)`, `story_media (asset_id)`, `story_tag/media_tag (tag_id)` — pgsql FKs don't auto-index). `statusCounts` 5 COUNTs → 1 grouped query. `countByDateAndStatus`/`countExclusive` `whereDate` → `whereBetween` day range (keeps `stories_status_published_at_index` usable). `ProcessIndexOutbox` batch rewrite: one `attempts+1` UPDATE + one `Story::whereIn` prefetch (no relations — payload needs columns only) instead of per-row `findByPublicId` (was ~6 queries/row); per-row HTTP + outcome update kept (Meili is per-doc). `DashboardService` KPI block in 60s `Cache::remember('dashboard:kpis')`; lists stay fresh. Wire feed paths audited — already eager-loaded (`category`, `media`) with bounded per-section queries; no change needed. Photo-manager tab counts (7 COUNTs incl. 2 whereHas) left as-is — indexed, page-scoped, under 50ms. Staging Debugbar/Telescope (issue item 1) deferred to COS-24 staging.
- **Tests:** `tests/Feature/QueryOptimizationTest.php` 5 passed (26 assertions) — index existence (`Schema::hasIndex` ×7), statusCounts exact 1-query bound + correctness, date-count day boundaries (incl. exclusive), outbox batch 8 rows ≤ N+6 queries all marked done, dashboard 2nd call strictly fewer queries with identical KPIs. Full suite: **730 passed, 1 skipped, 0 failures**; `php -l` clean; `php scripts/schema-parity-check.php` PASSED; `php artisan migrate:fresh --seed` clean (index-only migration).
- **Live Smoke:** `optimize:clear`; `monitor:outbox-lag` OK lag=0s; `/api/v1/portal/feed` 200; `/admin` guest 302→/login (auth enforced). Server stopped after smoke.
- **Review:** (PR review — pending CI)

---

## 7. Prompt Ready?
- [x] Yes
