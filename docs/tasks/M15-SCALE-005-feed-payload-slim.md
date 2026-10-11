# Task: M15-SCALE-005 — Feed payload slim: `body_html` out of list payloads, portal lazy-expand

**Status:** ⏳ Pending
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

- [ ] Feed list payload has no `body_html`; story detail endpoint still returns it
- [ ] Portal expand still shows full body (lazy-fetched), Bangla + English
- [ ] Feed cache entry size reduced (assert Redis payload length before/after in a test)
- [ ] `/api/v1/portal/story/{id}` responses carry long-lived `Cache-Control` (immutable-after-publish)
