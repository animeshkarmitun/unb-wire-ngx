# Task: M13-API-005 — Kill/Correction Tombstones in Programmatic Feed (FR-DST-008)

**Status:** ✅ Completed
**Dependencies:** M13-API-002
**Parent ADR:** DEC-007, FR-DST-008

---

## 1. Contract (What)
- **Endpoint:** `GET /api/v1/feed?since=<cursor>&limit=<n>`
- **Behavior:**
  - Published stories appear with `status: "published"`, `is_killed: false`, `killed_at: null`.
  - Previously published stories that were killed appear with `status: "killed"`, `is_killed: true`, `brief: "STORY KILLED / RETRACTED"`, and `killed_at: "<ISO8601>"`.
  - Draft or in-review stories killed before publication are excluded.
  - Entitlement filtering on language and category strictly applies to tombstones as well.

---

## 2. Logic (How)
1. In `ClientFeedController::index()`, extend query builder from `where('status', 'published')` to:
   ```php
   $q = Story::with('category')->where(function ($query) {
       $query->where('status', 'published')
           ->orWhere(function ($q2) {
               $q2->where('status', 'killed')->whereNotNull('published_at');
           });
   });
   ```
2. Map feed item attributes to include `is_killed`, `status`, and `killed_at` (derived from `updated_at` when killed).
3. If killed, overwrite `brief` with retraction warning `"STORY KILLED / RETRACTED"`.

---

## 3. Context (Where)
- **Files Modified:**
  - `app/Http/Controllers/Api/ClientFeedController.php`
- **Tests Created:**
  - `tests/Feature/Api/FeedTombstoneTest.php`

---

## 4. Test Criteria
- [x] Published story appears in feed with `status: "published"` and `is_killed: false`.
- [x] Previously published story that was killed appears as tombstone with `status: "killed"`, `is_killed: true`, and `killed_at` timestamp.
- [x] Story killed from draft before ever being published is excluded from feed.
- [x] Entitlement rules (language/category) continue to filter tombstones.

---

## 5. Completion Notes
- **Shipped:** Added query predicate for previously published killed stories, updated feed item mapping with tombstone fields and retraction notice.
- **Tests:** `php artisan test --filter=FeedTombstoneTest` (4 passed, 16 assertions).
