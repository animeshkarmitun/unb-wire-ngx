# Task: M15-MEDIA-001 — Featured Image & Caption Display Fixes

**Status:** ✅ Completed
**Dependencies:** M8-WIZ-003 (media step — completed as shell)
**Parent ADR:** DEC-007, app-data/add-news.html

---

## 1. Contract (What)

Fix featured image display + caption system across story reader and wire service views.
The schema (`story_media` pivot) and repository (`syncMedia`) are correct — the issues are
in the model layer (missing relationship) and blade views (wrong column names, missing caption override fallback).

---

## 2. Checklist

### P1 — Core Fixes
- [x] `Story.php`: Add `featuredMedia()` scoped relationship (`wherePivot('role','featured')`)
- [x] `story-view.blade.php` L388: Use `pivot->caption_override ?: $feat->caption` fallback
- [x] `wire-service-view.blade.php`: Fix `file_path` → `original_path` at hero (L142), card (L204), rail (L261)
- [x] `wire-service-view.blade.php`: Use `Storage::url()` instead of `asset('storage/')` for consistency
- [x] Verify: `php -l` clean on all changed files
- [x] Verify: 119 tests passed (0 failures)

### P2 — Edit Modal Wiring
- [x] Wire up `#editOverlay` JS click handlers (open, close, apply, remove)
- [x] Add `updateAttachedMedia()` Livewire method for updating captions & rights
- [x] Attached → Featured promotion toggle (`promoteToFeatured()`)

### P3 — Enhanced UX
- [x] Cropper.js toolbar restore (aspect ratios: Free, 16:9, 4:3, 1:1, zoom in/out, rotate, flip, reset)
- [x] Drag-to-reorder via SortableJS on `#attachGrid` wired to `reorderMedia()`
- [x] File upload via `WithFileUploads` for featured image and attached media with progress state
- [x] Real image thumbnail rendering in featured preview, attach grid tiles, and archive modal

---

## 3. Context (Where)

- **Files Modified:**
  - `app/Models/Story.php` — added `featuredMedia()` scoped relationship
  - `app/Repositories/StoryRepository.php` — added `media_assets.original_path` to `getAttachedMedia()`
  - `app/Livewire/Admin/AddNews.php` — added `WithFileUploads`, `uploadMedia()`, `reorderMedia()`, `updateAttachedMedia()`, `promoteToFeatured()`
  - `resources/views/livewire/admin/story-view.blade.php` — caption override fallback
  - `resources/views/livewire/admin/wire-service-view.blade.php` — `file_path` → `original_path` & `Storage::url()`
  - `resources/views/livewire/admin/partials/add-news-step2-media.blade.php` — real thumbnails, file upload inputs, loading indicators
  - `resources/views/livewire/admin/partials/add-news-modals.blade.php` — Cropper.js toolbar, real photo archive thumbnails
  - `resources/js/app.js` & `resources/js/wizard/wizard-main.js` — Cropper.js & SortableJS integration
  - `resources/css/add-news-wizard.css` & `resources/css/app.css` — Cropper styles & edit modal tools styling
  - `tests/Feature/AddNewsTest.php` — unit test coverage for upload, reorder, caption update, and promotion

---

## 4. Completion Notes

- **P1**: Added `featuredMedia()` BelongsToMany on `Story.php`. Fixed caption fallback in `story-view.blade.php`. Fixed `file_path` → `original_path` across `wire-service-view.blade.php`.
- **P2**: Full Edit Media modal interactivity with Livewire syncing for per-story caption overrides, distribution rights, and "Set as featured" promotion.
- **P3**: Restored prototype-faithful Cropper.js toolbar (Free, 16:9, 4:3, 1:1, zoom, rotate, flip, reset), installed SortableJS for drag-and-drop tile reordering, enabled Livewire file uploads (`WithFileUploads`) with loading indicators, and wired real thumbnail URLs everywhere.
- **Verification**: `php -l` clean across all modified PHP and Blade files. `npm run build` compiled without errors. Full `AddNewsTest` suite passing (40 tests, 133 assertions).
