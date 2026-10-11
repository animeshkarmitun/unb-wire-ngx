# Task: M8-NEWS-003 — Advanced story filters (full-dataset, modular service layer)

**Status:** ✅ Completed
**Dependencies:** M8-NEWS-001, M15-SCALE-004 (staff Meilisearch)
**Parent ADR:** NFR §1 surface #1 (newsroom archive); DEC-016/DEC-019 unaffected

---

## 1. Contract (What)
- **Inputs / Validation:** Livewire public state on `NewsList` — `searchScope` (`all|headline|brief|body|caption`), `authorId` (`any` or int), `tagIds` (int[]), `dateField` (`any|published|created`), `dateMode` (`any|today|range`), `dateFrom`/`dateTo` (`Y-m-d` or `''`), `hasImage` (`any|with|without`). All whitelisted server-side by `StoryFilter::fromArray` — unknown values fall back to defaults (Oworia filters never leak engine strings).
- **Outputs / Response:** Same paginated table surface; pagination, select-all ids, and CSV export all honor the full filter set on the full qualified dataset (one shared filter pipeline).
- **Authorization:** unchanged (`rbac:stories,view`).

---

## 2. Logic (How)
1. `StoryFilter` DTO (`app/Support/StoryFilter.php`) — validated value object; computes the UTC date window (Dhaka boundary: inclusive `00:00` day-start, exclusive next-day) from mode `today`/`range` + field `published`/`created`.
2. `StoryFilterService::filter(Builder, StoryFilter)` (`app/Services/StoryFilterService.php`) — structured dimensions one private applier each (`applyAuthor/applyTags/applyDate/applyImage`) + text seam `applyText`:
   - `caption` scope → **SQL-only** (caption is not in the Meili staff index): `whereIn(id, story_media ⋈ media_assets caption/caption_override ILIKE)` subquery.
   - otherwise → `StaffSearchService::searchIds(StoryFilter)` (Meili path gains `attributesToSearchOn` scope restriction; scope `all` uses default searchable attrs) → `whereIn(public_id, ids)`; engine down → SQL ILIKE fallback on scoped columns (headline/brief/body_text; `all` = headline+brief+body_text).
3. `StaffSearchService::buildFilter` + payload fields unchanged (language/status/category_id already filterable).
4. `StoryRepository` slims to DTO signatures: `filteredList/filteredIds/filteredCount/exportQuery(StoryFilter)` — structured+text clauses come from the service; `applySort` (whitelist + tie-breaker) stays repository-owned, sort fields now carried in the DTO.
5. `NewsList` renders authors (distinct story owners in this language) + tags lists; all filter props reset pagination via a single `updated($name)` hook; chips bar shows/ dismisses active advanced filters; `Clear all` resets them.
6. UI apply policy: structured filters apply instantly on change (single indexed query per user action); free-text stays debounced-live (300ms, existing behavior).

---

## 3. Context (Where)
- **Files to Create:** `app/Support/StoryFilter.php`, `app/Services/StoryFilterService.php`, `tests/Unit/StoryFilterTest.php`, `docs/tasks/M8-NEWS-003-advanced-story-filters.md`
- **Files to Modify:** `app/Services/Search/StaffSearchService.php`, `app/Repositories/StoryRepository.php`, `app/Livewire/Admin/NewsList.php`, `resources/views/livewire/admin/news-list.blade.php`, `resources/css/news-desk.css`, `tests/Feature/NewsListTest.php`, `tests/Feature/StaffSearchTest.php`, `tests/Feature/Repositories/StoryRepositoryTest.php`, `docs/tasks/README.md`
- **Reference Files:** `app/Support/DisplayPrefs.php` (Dhaka tz), `app/Jobs/ProcessIndexOutbox.php` (staff payload), M15-SCALE-001 trgm fallback.

---

## 4. Prompt (For the coding AI)
> See §2. Do NOT widen Meili staff `searchableAttributes`; scope restriction happens per-query via `attributesToSearchOn`. Pivot-column caption search must use a `whereIn(id, subquery)` (whereHas pivot aliasing is unreliable). Keep `filteredIds` perPage param as-is (pre-existing select-all scope is noted, not widened here).

---

## 5. Test Criteria
- [ ] Unit: `StoryFilter::fromArray` defaults/whitelist; Dhaka `today` window boundary (fixture now in Dhaka tz)
- [ ] Feature: author / tag / has-image / date-today filters narrow results
- [ ] Feature: `caption` scope finds story via media caption (SQL path); `body` scope SQL fallback finds body text
- [ ] Feature: Meili ids path intersects with structured filters (HTTP-faked)
- [ ] Export + select-all honor advanced filters
- [ ] `php -l` clean; full suite pass; `npm run build`

---

## 6. Completion Notes
- **Shipped:** `App\Support\StoryFilter` DTO (whitelist-validated; Dhaka `today`/`range` UTC windows with exclusive upper edge; scope→Meili `attributesToSearchOn` + SQL-column maps). `App\Services\StoryFilterService` — per-dimension appliers (author/tags/date/image) + text seam (caption → SQL-only `story_media ⋈ media_assets` ILIKE subquery; other scopes → `StaffSearchService` ids `whereIn(public_id)` → trgm fallback on scoped columns; driver-aware `ilike`/`like`). `StoryRepository` slims to DTO signatures (`filteredList/filteredIds/filteredCount/exportQuery`) — one dataset definition shared by pagination, select-all and CSV export. `StaffSearchService` is scope-aware (engine unreachable for caption; per-query `attributesToSearchOn`). `NewsList` gains filter props + single `updated()` pagination-reset hook (filter-props only) + `toggleTag`/`clearFilters` + author/tag options; Blade gets scope dropdown, ⚙ Filters inline panel (author/date today|range+field switch/image segmented/tag chips) + active-chip bar; CSS for panel/segments/chips. Removed the legacy `buildFilterQuery` (folded into the service).
- **Tests:** `php artisan test --stop-on-failure` — 926 passed / 6 skipped (2982 assertions). New: `tests/Unit/StoryFilterTest.php` (6), 7 NewsList feature tests (author/tags/image/today-Dhaka/caption-scope/body-scope/selectAll+export), 2 StaffSearch tests (Meili-ids ∩ structured filters, `attributesToSearchOn` on scope).
- **Live Smoke:** `php artisan optimize:clear` executed; `/login` → `/admin/news/en` as `nahar@unbnews.org` (Admin) → 200, renders scope dropdown + Filters toggle/panel; tinker smoke vs live PG: today-window count query OK, caption subquery SQL composes with pg `ilike` casts, staff-engine probe → null → fallback path active (MeILI key empty in this env).
- **Review:** pending `unb-wire-reviewer` (pre-commit)

---

## 7. Prompt Ready?
- [x] Yes
