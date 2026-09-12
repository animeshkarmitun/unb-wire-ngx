# Task: M13-API-002 — Entitlement Enforcement on Programmatic Feed (FR-DST-002, FR-PRT-006)

**Status:** ✅ Completed
**Dependencies:** M13-API-001
**Parent ADR:** DEC-007, FR-DST-002, FR-PRT-006

---

## 1. Contract (What)
- **Endpoint:** `GET /api/v1/feed?since=<cursor>&limit=<n>`
- **Auth:** `client.api:feed:read` (API key with `feed:read` scope)
- **Response Shape:**
  ```json
  {
    "data": [
      {
        "public_id": "01...",
        "headline": "...",
        "brief": "...",
        "language": "en",
        "published_at": "2026-09-13T01:00:00+00:00",
        "is_breaking": false
      }
    ],
    "cursor": "..."
  }
  ```
- **Entitlement Rules:**
  - Client only receives stories matching their active package entitlements (`languages`, `category_ids`).
  - Responses cached for 60 seconds with per-client cache isolation (`feed:v1:{client_id}:{hash}`).

---

## 2. Logic (How)
1. In `ClientFeedController::index()`, retrieve authenticated `$client` from request attributes.
2. If client is present, call `EntitlementResolver::forClient($client)`.
3. Apply `whereIn('language', $ent['languages'])` if languages specified.
4. Apply `whereIn('category_id', $ent['category_ids'])` if category_ids specified.
5. Partition Redis cache key by client ID: `'feed:v1:'.$clientId.':'.md5($request->fullUrl())`.
6. Maintain base64 cursor pagination over the filtered dataset.

---

## 3. Context (Where)
- **Files Modified:**
  - `app/Http/Controllers/Api/ClientFeedController.php`
- **Tests Created:**
  - `tests/Feature/Api/ClientFeedEntitlementTest.php`

---

## 4. Test Criteria
- [x] Client with English-only package only receives English stories.
- [x] Client with category-restricted package only receives stories in those categories.
- [x] Clients with different package entitlements never receive each other's cached responses.
- [x] Cursor pagination works properly with entitlement filtering.
- [x] Rate limiting and existing API key tests continue to pass.

---

## 5. Completion Notes
- **Shipped:** Injected `EntitlementResolver` into `ClientFeedController`, applied entitlement filtering on language and categories, and added per-client cache key partitioning.
- **Tests:** `php artisan test --filter=ClientFeedEntitlementTest` (4 passed, 17 assertions) + `ClientApiKeyTest` (6 passed, 16 assertions).
