# Task: M13-API-003 — Entitlement Enforcement on Portal Feed & Story View (FR-PRT-002, FR-PRT-006)

**Status:** ✅ Completed
**Dependencies:** M13-API-001
**Parent ADR:** DEC-007, FR-PRT-002, FR-PRT-006

---

## 1. Contract (What)
- **Endpoints:**
  - `GET /api/v1/portal/feed?category=&language=&is_breaking=&search=&limit=`
  - `GET /api/v1/portal/story/{publicId}`
- **Auth:** `resolve.client:optional`
  - Unauthenticated guests receive public portal preview.
  - Authenticated portal users / API clients have their active package entitlements enforced.
- **Entitlement Rules:**
  - Feed queries filter by `whereIn('language', $ent['languages'])` and `whereIn('category_id', $ent['category_ids'])`.
  - Cache partitioned by client ID: `portal:feed:{client_id}:{hash}`.
  - Story show endpoint returns 404 if the authenticated client's package does not include the story's language or category.

---

## 2. Logic (How)
1. Add `resolve.client:optional` to `/portal/feed` and `/portal/story/{publicId}` in `routes/api.php`.
2. In `PortalController::feed()`, resolve client attributes. If present, query `EntitlementResolver::forClient($client)` and filter query builder by entitled languages and category IDs.
3. Compute cache key with client ID: `portal:feed:{$clientId}:...`.
4. In `PortalController::show()`, check whether story's language and category match client entitlements. Return 404 if unauthorized to prevent cross-package information disclosure.

---

## 3. Context (Where)
- **Files Modified:**
  - `routes/api.php`
  - `app/Http/Controllers/Api/PortalController.php`
- **Tests Created:**
  - `tests/Feature/Api/PortalFeedEntitlementTest.php`

---

## 4. Test Criteria
- [x] Portal user with English-only package only receives English stories in portal feed.
- [x] Portal user with category restriction only receives matching category in portal feed.
- [x] Portal user cannot view an unentitled story via `/portal/story/{publicId}` (returns 404).
- [x] Portal user can view an entitled story with full body and media details.
- [x] Unauthenticated guests continue to see the public feed without regression.

---

## 5. Completion Notes
- **Shipped:** Wired `EntitlementResolver` into `PortalController` for both `feed()` and `show()`, wrapped routes in `resolve.client:optional`, and partitioned portal feed cache.
- **Tests:** `php artisan test --filter=PortalFeedEntitlementTest` (4 passed, 9 assertions) and `PortalApiTest` (28 passed, 356 assertions).
