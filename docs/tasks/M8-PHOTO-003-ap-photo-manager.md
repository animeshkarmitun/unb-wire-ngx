# Task: M8-PHOTO-003 — AP Photo Manager faithful

**Status:** ✅ Completed
**Dependencies:** M8-UI-002, M8-PHOTO-001
**Parent ADR:** app-data/ap-photo-manager.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/ap-photos` `auth,verified,rbac:media,view`; query: search, category (13 categories), subCategory (5 regions), tag, perPage (default 11, incremental reveal by 5).
- **Outputs / Response:**
  - Breadcrumb: `Home / AP Photo Manager`.
  - Topbar with `Sync log` modal toggle and `Sync now` button with real-time feedback and toast notifications.
  - `.sync-note`: auto-sync status banner with green checkmark and timestamp metadata.
  - Filter card with search input, 13-option category select, 5-option subcategory select, tag input, Search, and Reset buttons.
  - `.cat-chips`: 13 category pill chips horizontally scrollable with active navy state synchronized with category select.
  - Result counter: `Showing X of 1,248 AP photos`.
  - Photo grid: responsive CSS grid (`repeat(auto-fill, minmax(235px, 1fr))`) with cards featuring `g1`–`g8` gradients, AP badge top-left, hover expand button top-right, 2-line clamped caption, category pill (`.sports`, `.world`), date, `Attach to story` button with green `✓ Attached` state, and `Download original` button.
  - Load more button (`Load more photos (N)`) incrementing visible photos.
  - Centered 880px Lightbox Modal: split 1.5fr / 1fr layout with large preview image, Serif caption, and 5 metadata rows (Credit, Category, Wire date, Dimensions, Downloads), plus `Attach to story` and `Download original` actions.
  - AP Wire Sync Log Modal: table displaying sync history, healthy wire feed status, photos ingested, channel, and latency.
- **Authorization:** `media` module per `roles` matrix.

---

## 2. Logic (How)
1. Created `resources/css/ap-photo-manager.css` importing 1:1 CSS from `app-data/ap-photo-manager.html` and registered into `resources/css/app.css`.
2. Updated `database/seeders/MediaSeeder.php` to seed the 16 prototype AP photos (Zelenskyy, Pope Leo, Ukrainian flag, Bangladesh cricket, Jatiya Sangsad, DSE stocks, etc.) with accurate dimensions, categories, dates, downloads, and gradients (`g1`–`g8`).
3. Added `media(): BelongsToMany` relationship to `Story` model for full bidirectional attachment support.
4. Refactored `app/Livewire/Admin/ApPhotoManager.php` with complete filters, search, category chips, incremental load more, lightbox modal, story attachment, download tracking, sync now, and sync log drawer.
5. Implemented `resources/views/livewire/admin/ap-photo-manager.blade.php` matching 1:1 prototype markup.
6. Created PHPUnit feature test suite `tests/Feature/ApPhotoManagerTest.php` (12 tests, 50 assertions).
7. Created Playwright E2E test suite `tests/e2e/ap-photo-manager-faithful.spec.ts` (5 tests).

---

## 3. Context (Where)
- **Files Created / Modified:**
  - `resources/css/ap-photo-manager.css`
  - `resources/css/app.css`
  - `app/Livewire/Admin/ApPhotoManager.php`
  - `resources/views/livewire/admin/ap-photo-manager.blade.php`
  - `app/Models/Story.php`
  - `database/seeders/MediaSeeder.php`
  - `tests/Feature/ApPhotoManagerTest.php`
  - `tests/e2e/ap-photo-manager-faithful.spec.ts`
  - `docs/tasks/M8-PHOTO-003-ap-photo-manager.md`
  - `docs/tasks/README.md`

---

## 4. Test Criteria
- [x] Page header, breadcrumb, topbar actions, sync note, filter bar, and 13 category chips render
- [x] Category chips and category select filter photos interactively in real-time
- [x] Search input filters photo captions and reset button restores default view
- [x] Sub-category and tag filters refine results
- [x] Incremental reveal button (`Load more photos`) expands visible cards
- [x] Clicking photo card or expand button opens centered 880px lightbox with 5 metadata rows
- [x] `Attach to story` transitions to `✓ Attached` on card and `✓ Attached to draft` in lightbox, linking to active draft story
- [x] `Download original` increments download count and dispatches toast
- [x] `Sync now` button triggers sync and updates last synced time and count
- [x] `Sync log` button opens modal showing AP wire poll history
- [x] Automated PHPUnit tests pass (`ApPhotoManagerTest`: 12/12 passed)
- [x] Automated Playwright E2E tests pass (`ap-photo-manager-faithful.spec.ts`: 5/5 passed)

---

## 5. Completion Notes
- **Shipped:** 1:1 faithful AP Photo Manager with 5-filter toolbar, 13 category chips, showing counter, photo grid with AP badge and hover expand, centered 880px lightbox modal with 5 metadata rows, attach-to-story workflow, download trigger, sync log drawer/modal, and sync now action.
- **Tests:** `php artisan test --filter=ApPhotoManagerTest` (12 passed, 50 assertions); full suite: 142 passed (419 assertions).
- **E2E:** `npx playwright test tests/e2e/ap-photo-manager-faithful.spec.ts` (5 passed).
- **Live Smoke:** Clear compiled caches (`config:clear`, `route:clear`, `view:clear`), `/admin/ap-photos` renders 200 with all components.
