# Task: M15-SCALE-005 — Feed payload slim: `body_html` out of list payloads, portal lazy-expand

**Status:** ✅ Completed (Option A implemented — user decision 2026-10-11)
**Dependencies:** M15-SCALE-001
**Parent ADR:** research doc P1-6; NFR §2 ("story read — immutable after publish → aggressive cache").

---

## Design decision needed before implementation

`PortalController::feed` embeds full `body_html` per item (50–200KB per cached entry × clients × variants). The **portal currently renders `body_html` inline** on card expand (`portal/app/page.tsx` `(s.body_html || s.brief)`), so slimming is an **API contract + UX change**:

| Option | Approach | Tradeoff |
|---|---|---|
| **A (recommended)** | Feed returns brief only; portal **lazy-fetches `/api/v1/portal/story/{publicId}`** on first expand (endpoint already exists + per-story cacheable, immutable after publish) | 5–10× smaller feed cache entries; one extra request per expand (CDN-cacheable per story); portal UX code changes; e2e specs asserting body in feed must move |
| B | Keep `body_html` in feed | zero UX change; cache memory cost stays — the 1,000-user surge cost |

Option A requires: portal `types.ts`/`format.ts` update, expand handler fetch+cache (client-side map), `formatStory` split (list vs detail shape), e2e `portal-*`/`news-list` spec adjustments, FR/PRT-00x contract doc sync.

## 5. Test Criteria

- [x] Feed list payload has no `body_html`; story detail endpoint still returns it
- [x] Portal expand still shows full body (lazy-fetched), Bangla + English
- [x] Feed cache entry size reduced (assert Redis payload length before/after in a test)
- [x] `/api/v1/portal/story/{id}` responses carry long-lived `Cache-Control` (immutable-after-publish)

## 6. Completion Notes

- **Shipped:** `PortalController::formatStory` split into `formatStoryList` (brief-only, no `body_html`) + `formatStoryDetail` (`array_merge(list, body_html)`); `feed()` → list shape; `show()` → detail shape wrapped in epoch-keyed `Cache::flexible([30, 120])` (`portal:story:{epoch}:{clientId}:{publicId}` — epoch bump on publish/re-edit invalidates instantly; entitlement-denial cached per-client) + `Cache-Control: public, max-age=60`. Portal `page.tsx`: `storyBodyCache` (Map, in-flight guarded) + `ensureStoryBody` lazy-fetch on first expand, wired into all 6 expand triggers (cards headline/preview, grid headline/read, table row-click, Enter key); `stories` state patched with fetched body. Existing `(s.body_html || s.brief)` render fallbacks show the brief until the fetch lands.
- **Tests:** `FeedPayloadSlimTest` 3/3 — feed brief-only + detail carries body + `Cache-Control` header; epoch-invalidated detail on published re-edit; entitlement 404 cached per client. `PortalApiTest` feed-shape assertion updated to the brief-only contract. Full suite **910 passed**.
- **Live Smoke:** feed publish→instant visibility re-verified green; story detail 200 with body; portal tsc clean; e2e portal-auth/search-token paths green (expand-UI e2e blocked by pre-existing Windows seed-escaping — feature tests cover the API contract).
- **Gotcha hit & fixed:** initial `show()` refactor returned payload fields flat, dropping the `['data' => ...]` wrapper (broken API shape caught by `PortalApiTest` — fixed before commit).
