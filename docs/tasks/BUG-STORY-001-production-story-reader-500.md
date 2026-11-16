# Task: BUG-STORY-001 — Production Story Reader 500 Server Error Investigation & Resolution

**Status:** ✅ Complete & Deployed to Production  
**Priority:** High (Production Error)  
**Target Environment:** Production (`http://147.93.171.193:8090`) & Local  
**Affected Route:** `GET /admin/story/{publicId}` (`admin.story`)  
**Trigger:** Clicking the eye icon (Preview / Story Reader) on `/admin/news/{language}`  
**Parent Specifications:** `docs/tasks/M8-STORY-001-story-reader.md`, `app-data/story.html`, `app-data/v1-functional-requirements.md` (FR-NWS-019)

---

## 1. Issue Summary

When clicking the eye action icon on a newly imported or live story on the production admin dashboard (`http://147.93.171.193:8090/admin/news/en`), the application navigated to `/admin/story/{publicId}` and returned:

```
500 | SERVER ERROR
```

---

## 2. Root Causes Identified & Fixed

1. **Fatal TypeError in Blade Template (`story-view.blade.php:605`):**
   ```blade
   @if(isset($evt->payload['fields']))
       {{ implode(', ', $evt->payload['fields']) }}
   @endif
   ```
   When `payload['fields']` contained a string rather than an array, PHP threw:
   `implode(): Argument #2 ($array) must be of type ?array, string given`.
   **Fix:** `{{ is_array($evt->payload['fields']) ? implode(', ', $evt->payload['fields']) : $evt->payload['fields'] }}`.

2. **Fatal ParseError under PHP `short_open_tag` (`story-view.blade.php:83`):**
   ```javascript
   const xml =
       '<?xml version="1.0" encoding="UTF-8"?>\n' +
   ```
   Because PHP in the Docker container had `short_open_tag` enabled, the Blade compiler output literal `<?xml`, which PHP evaluated as a PHP opening tag followed by identifier `version`, throwing `ParseError: syntax error, unexpected identifier "version"`.
   **Fix:** Changed to `'<' + '?xml version="1.0" encoding="UTF-8"?>\n'` which neutralizes the PHP short open tag parser while emitting the identical XML declaration string in client JS.

3. **Array Offset Guard on Media Derivatives (`story-view.blade.php:372`):**
   **Fix:** Guarded with `is_array($feat->derivatives) && isset($feat->derivatives['grad'])`.

4. **Eager-Load Partitioned `deliveries` Table:**
   `StoryRepository::findWithAllRelations` and `StoryView::render()` now eager-load `'deliveries'` to ensure zero lazy-loading violations or N+1 queries.

5. **Null-Safe Auth & Polymorphic DisplayPrefs:**
   - `auth()->check()` added before `RbacService::can()` in `StoryView`.
   - `DisplayPrefs::format` handles `\DateTimeInterface|string|null` safely.

---

## 3. Step-by-Step Action Plan & Status

- [x] **Step 1: Reproduce Locally with Enriched Story Data**
  - Seeded story with workflow events, media, versions, and deliveries on local PostgreSQL (`http://unb-wire-ngx.test`).
  - Reproduced the exact 500 error in `storage/logs/laravel.log`: `implode(): Argument #2 ($array) must be of type ?array, string given`.
- [x] **Step 2: Surgical Fixes in Code**
  - Hardened `resources/views/livewire/admin/story-view.blade.php` (lines 83, 372, and 605).
  - Added `'deliveries'` eager-loading to `StoryRepository::findWithAllRelations` and `StoryView::render()`.
  - Added null guard to `StoryView::getCanViewHistoryProperty` and `getCanEditStoryProperty`.
  - Extended `DisplayPrefs::format` to accept `\DateTimeInterface|string|null`.
- [x] **Step 3: Verification & Quality Gates**
  - Tested locally: `http://unb-wire-ngx.test/admin/story/01M7HJ6YQ9R8GJY96HXJVK981V` returns `200 OK`.
  - All 11 `StoryViewTest` tests pass.
  - `php scripts/schema-parity-check.php` passes.
  - `npm run build` completed cleanly.
- [x] **Step 4: Production Deployment & GitHub Actions CI/CD Pipeline**
  - Configured automated CD in `.github/workflows/ci.yml` via SSH key deployment.
  - Configured repository secrets (`VPS_HOST`, `VPS_USER`, `VPS_PORT`, `VPS_PATH`, `VPS_SSH_KEY_B64`).
  - Added deploy key to `/root/.ssh/authorized_keys` on production VPS (`147.93.171.193`).
  - GitHub Actions CI/CD run `#43871795342` passed all tests and auto-deployed to production in 11s.
- [x] **Step 5: Production Smoke Verification**
  - `http://147.93.171.193:8090/admin/story/01M7HR53W8PE9TXCWQBD39E2A4` ("Metro Rail"): **`200 OK`**
  - `http://147.93.171.193:8090/admin/story/01M7HJ6YQ9R8GJY96HXJVK981V` ("Dhaka library"): **`200 OK`**
  - `http://147.93.171.193:8090/admin/story/01M7HZX23RAXPDBDCS05E7VDZA` (Canary probe): **`200 OK`**
  - One Group ERP (`one-erp-*` containers on port 80) completely untouched and isolated throughout.
