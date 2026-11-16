# Task: BUG-STORY-001 — Production Story Reader 500 Server Error Investigation & Resolution

**Status:** 🔧 Ready for Deployment & Smoke Test  
**Priority:** High (Production Error)  
**Target Environment:** Production (`http://147.93.171.193:8090`) & Local  
**Affected Route:** `GET /admin/story/{publicId}` (`admin.story`)  
**Trigger:** Clicking the eye icon (Preview / Story Reader) on `/admin/news/{language}`  
**Parent Specifications:** `docs/tasks/M8-STORY-001-story-reader.md`, `app-data/story.html`, `app-data/v1-functional-requirements.md` (FR-NWS-019)

---

## 1. Issue Summary

When clicking the eye action icon on a newly imported or live story (e.g. `public_id: 01M7HPX2NSPBWBM54S6V7NC4V0`) on the production admin dashboard (`http://147.93.171.193:8090/admin/news/en`), the application navigates to `/admin/story/01M7HPX2NSPBWBM54S6V7NC4V0` and returns:

```
500 | SERVER ERROR
```

Because the story is visible in the newsroom table, the record exists in PostgreSQL. A `404` is not returned, meaning `Story::where('public_id', $publicId)->firstOrFail()` succeeds, and the failure occurs during component mounting, eager loading, or Blade view evaluation.

---

## 2. Root Cause Identified & Verified Locally

1. **Fatal TypeError in Blade Template (`story-view.blade.php:605`):**
   ```blade
   @if(isset($evt->payload['fields']))
       {{ implode(', ', $evt->payload['fields']) }}
   @endif
   ```
   When `payload['fields']` contains a string rather than an array, PHP throws:
   `local.ERROR: implode(): Argument #2 ($array) must be of type ?array, string given`.
   **Fix:** `{{ is_array($evt->payload['fields']) ? implode(', ', $evt->payload['fields']) : $evt->payload['fields'] }}`.

2. **Array Offset Guard on Media Derivatives (`story-view.blade.php:372`):**
   **Fix:** Guard with `is_array($feat->derivatives) && isset($feat->derivatives['grad'])`.

3. **Eager-Load Partitioned `deliveries` Table:**
   `StoryRepository::findWithAllRelations` and `StoryView::render()` now eager-load `'deliveries'` to ensure zero lazy-loading violations or N+1 queries.

4. **Null-Safe Auth & Polymorphic DisplayPrefs:**
   - `auth()->check()` added before `RbacService::can()` in `StoryView`.
   - `DisplayPrefs::format` handles `\DateTimeInterface|string|null` safely.

---

## 3. Step-by-Step Action Plan & Status

- [x] **Step 1: Reproduce Locally with Enriched Story Data**
  - Seeded story with workflow events, media, versions, and deliveries on local PostgreSQL (`http://unb-wire-ngx.test`).
  - Reproduced the exact 500 error in `storage/logs/laravel.log`: `implode(): Argument #2 ($array) must be of type ?array, string given`.
- [x] **Step 2: Surgical Fixes in Code**
  - Hardened `resources/views/livewire/admin/story-view.blade.php` (lines 372 and 605).
  - Added `'deliveries'` eager-loading to `StoryRepository::findWithAllRelations` and `StoryView::render()`.
  - Added null guard to `StoryView::getCanViewHistoryProperty` and `getCanEditStoryProperty`.
  - Extended `DisplayPrefs::format` to accept `\DateTimeInterface|string|null`.
- [x] **Step 3: Verification & Quality Gates**
  - Tested locally: `http://unb-wire-ngx.test/admin/story/01M7HJ6YQ9R8GJY96HXJVK981V` returns `200 OK` (Body length: 95,776 bytes).
  - All 11 `StoryViewTest` tests pass.
  - `php scripts/schema-parity-check.php` passes.
  - `npm run build` completed cleanly.
- [ ] **Step 4: Deployment & Production Smoke**
  - Commit and push to origin.
  - Deploy to production VPS.
  - Verify `http://147.93.171.193:8090/admin/story/01M7HPX2NSPBWBM54S6V7NC4V0` renders `200 OK`.
