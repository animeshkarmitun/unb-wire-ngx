# Task: M15-SCALE-004 — Admin/newsroom search → Meilisearch (staff surface, all statuses)

**Status:** ✅ Completed (Option A implemented — staff index, user decision 2026-10-11)
**Dependencies:** M15-SCALE-001 (trgm interim fix shipped), M4-SEARCH-001
**Parent ADR:** NFR §1 surface #1 (newsroom archive: full text/headline/desk/category/date/**status**). Currently admin list search uses SQL `LIKE` on headline+brief (trgm-indexed since M15-SCALE-001 — fast enough for ~50 admins) and cannot search body text.

---

## Design decision needed before implementation

The client-facing Meilisearch `main` index only holds **published** docs (outbox writes on publish/kill/re-edit). Staff search must cover **draft/in_review/changes_requested/approved** too. Options:

| Option | Approach | Tradeoff |
|---|---|---|
| **A (recommended)** | New `staff` Meilisearch index; outbox `upsert` enqueued on **createDraft + every updateDraft (debounced) + every transition**, not just publish | one more index to size/rebuild (~same docs as main + drafts); outbox volume grows with autosaves — debounce required |
| B | Query `main` for published + trgm SQL fallback for non-published | two code paths; body-text search still SQL-only |
| C | Keep trgm SQL only (status quo post-M15-SCALE-001) | zero new infra; body-text search stays out of reach; doesn't meet NFR surface #1 "full story text" |

## 1. Contract (What)

- **Inputs:** admin list `search` param (headline/body/full-text), existing filters (language, status, category).
- **Outputs:** Meilisearch-driven ID set → repository hydrates (`whereIn public_id` + eager loads); graceful degradation to chronological feed if search down (NFR §5).
- **Authorization:** `RbacService.assertCan(user, module, action)` unchanged; internal notes never searchable via client-reachable indexes (staff index must never be exposed through portal tenant tokens).

## 3. Context (Where)

- `StoryRepository::buildFilterQuery` (search branch), `ProcessIndexOutbox` (new `staff` index routing), `StoryService` (outbox enqueue sites), `EntitlementResolver`/`TenantTokenIssuer` (ensure staff index NOT tenant-token reachable), `tests/Feature/*Search*`.

## 5. Test Criteria

- [x] Draft (never published) findable by owner/desk in admin search, not in portal search/index
- [x] Search down → admin list still loads (fallback), alert raised
- [x] Outbox lag monitor covers `staff` index

## 6. Completion Notes

- **Shipped:** `StaffSearchService` (staff index, server-side master-key only, settings ensure-once with 24h cache flag); `App\Support\IndexOutbox::enqueue` (pending-row dedupe + afterResponse dispatch — autosave bursts collapse to one row per document); staff upserts enqueued on createDraft/updateDraft (all statuses)/transition/embargo-lift/archive-sweep; `StoryRepository::buildFilterQuery` search branch rides Meilisearch ids (`whereIn public_id`) with trgm LIKE fallback on null; `ProcessIndexOutbox` accepts `staff` payloads. `TenantTokenIssuer` rules cover main/archive/media only — staff is tenant-token-unreachable by construction (asserted in test).
- **Tests:** `StaffSearchTest` 6/6 — Meili id resolution incl. non-published, filter payload (language/status/category), SQL fallback on engine 500, tenant-token rules exclude staff, enqueue dedupe across saves, lift/sweep enqueue staff rows. Full suite **910 passed**.
- **Live Smoke:** portal e2e run — 15 passed; 8 failed all proven environmental (Windows cmd can't run the specs' `\$u`-escaped / `2>/dev/null` tinker seed commands — reproduced identically manual, app path works via `$u` variant). Portal `tsc --noEmit` clean.
- **Notes:** layering — `StoryRepository` resolves `StaffSearchService` via `app()` (single call site; documented deviation from repo-no-services convention for search infra). Dedupe helper intentionally NOT applied to existing main/archive raw inserts (surgical; they already dispatch at their sites).
