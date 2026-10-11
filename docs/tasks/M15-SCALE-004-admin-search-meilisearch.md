# Task: M15-SCALE-004 — Admin/newsroom search → Meilisearch (staff surface, all statuses)

**Status:** ⏳ Pending
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

- [ ] Draft (never published) findable by owner/desk in admin search, not in portal search/index
- [ ] Search down → admin list still loads (fallback), alert raised
- [ ] Outbox lag monitor covers `staff` index
