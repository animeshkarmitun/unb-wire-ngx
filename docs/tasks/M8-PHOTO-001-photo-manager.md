# Task: M8-PHOTO-001 — UNB Photo Manager faithful

**Status:** ✅ Completed
**Dependencies:** M8-UI-002
**Parent ADR:** app-data/unb-photo-manager.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/photos` `auth,verified,rbac:media,view`; query: tab (`all`, `field`, `review`, `library`, `packaged`, `published`, `embargo`), search, photographer, category, sort (`new`, `dl`), unattachedOnly.
- **Outputs / Response:** Justified flex grid (`--r` aspect ratio preservation, 172px height, `.selected` outline, `.in-stack` outline, burst stack expansion `▤ +N` with cover selection), 7 workflow tabs with live count pills and crimson `.alert` badges, hover-reveal checkboxes, status pills with `STATUS_META` colors, sticky inspector side panel (`1fr 350px`) with editable metadata, client usage statistics, and bulk action bar with `JSZip` client-side photo/caption download.
- **Authorization:** `media` module per `roles` matrix.

---

## 2. Logic (How)
1. Created `resources/css/photo-manager.css` importing 1:1 CSS from `app-data/unb-photo-manager.html` and registered into `resources/css/app.css`.
2. Refactored `Livewire/Admin/PhotoManager.php` with 7 workflow tabs, dynamic query builders, live tab counts, burst stack cover selection, multi-select bulk actions (`bulkApprove`, `bulkAssignPackage`), sticky inspector (`saveAssetMetadata`, `approveInspected`, `linkAssetStory`), and upload/drag-drop processing.
3. Created `Database/Seeders/MediaSeeder.php` seeding 28 realistic assets across categories, photographers, packages, burst series, and field batches matching prototype.
4. Created `resources/views/livewire/admin/photo-manager.blade.php` matching 1:1 layout with JSZip integration.

---

## 3. Context (Where)
- **Files Created / Modified:**
  - `resources/css/photo-manager.css`
  - `resources/css/app.css`
  - `app/Livewire/Admin/PhotoManager.php`
  - `resources/views/livewire/admin/photo-manager.blade.php`
  - `app/Models/MediaAsset.php`
  - `database/seeders/MediaSeeder.php`
  - `database/seeders/DatabaseSeeder.php`
  - `tests/Feature/PhotoManagerTest.php`
  - `tests/e2e/photo-manager-faithful.spec.ts`

---

## 4. Test Criteria
- [x] 7 workflow tabs counts correct (`all`, `field`, `review`, `library`, `packaged`, `published`, `embargo`)
- [x] Justified grid preserves aspect ratio with `--r` CSS property
- [x] Inspector lazy loads, updates caption/photographer/location/keywords/package, and links stories
- [x] Bulk bar triggers on selection and provides approve, package assign, and JSZip download
- [x] Burst series expands inline and allows setting stack cover
- [x] Automated PHPUnit tests pass (`PhotoManagerTest`: 7/7 passed)
- [x] Automated Playwright E2E tests pass (`photo-manager-faithful.spec.ts`: 4/4 passed)

---

## 5. Completion Notes
- **Shipped:** 1:1 faithful UNB Photo Manager with justified grid, 7 tabs with live alert count badges, sticky inspector, burst series, bulk actions, and JSZip export.
- **Tests:** `php artisan test --filter=PhotoManagerTest` (7 passed, 51 assertions).
- **E2E:** `npx playwright test tests/e2e/photo-manager-faithful.spec.ts` (4 passed).
- **Live Smoke:** Clear compiled caches (`config:clear`, `route:clear`, `view:clear`), `/admin/photos` renders 200 with all components.
