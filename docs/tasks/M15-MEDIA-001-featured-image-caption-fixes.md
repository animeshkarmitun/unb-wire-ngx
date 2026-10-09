# Task: M15-MEDIA-001 — Featured Image & Caption Display Fixes

**Status:** 🔧 In Progress
**Dependencies:** M8-WIZ-003 (media step — completed as shell)
**Parent ADR:** DEC-007, app-data/add-news.html

---

## 1. Contract (What)

Fix featured image display + caption system across story reader and wire service views.
The schema (`story_media` pivot) and repository (`syncMedia`) are correct — the issues are
in the model layer (missing relationship) and blade views (wrong column names, missing caption override fallback).

---

## 2. Checklist

### P1 — Core Fixes (this task)
- [x] `Story.php`: Add `featuredMedia()` scoped relationship (`wherePivot('role','featured')`)
- [x] `story-view.blade.php` L388: Use `pivot->caption_override ?: $feat->caption` fallback
- [x] `wire-service-view.blade.php`: Fix `file_path` → `original_path` at hero (L142), card (L204), rail (L261)
- [x] `wire-service-view.blade.php`: Use `Storage::url()` instead of `asset('storage/')` for consistency
- [x] Verify: `php -l` clean on all changed files
- [x] Verify: 119 tests passed (0 failures)
- [ ] Verify: story-view renders featured image + correct caption (needs live smoke)
- [ ] Verify: wire-service-view renders real thumbnails (needs live smoke)

### P2 — Edit Modal Wiring (separate task)
- [ ] Wire up `#editOverlay` JS click handlers
- [ ] Add `updateAttachedCaption()` Livewire method
- [ ] Attached → Featured promotion toggle

### P3 — Enhanced UX (separate task)
- [ ] Cropper.js toolbar restore
- [ ] Drag-to-reorder (SortableJS)
- [ ] File upload via `WithFileUploads`

---

## 3. Context (Where)

- **Files to Modify:**
  - `app/Models/Story.php` — add relationship
  - `resources/views/livewire/admin/story-view.blade.php` — caption override
  - `resources/views/livewire/admin/wire-service-view.blade.php` — column name fix

---

## 4. Completion Notes

- **`Story.php`**: Added `featuredMedia()` BelongsToMany scoped to `wherePivot('role', 'featured')`. Reuses `media()` base relation.
- **`story-view.blade.php` L388**: Caption now reads `$feat->pivot->caption_override` first, then falls back to `$feat->caption`, then `$story->brief`.
- **`wire-service-view.blade.php`**: Fixed 3 occurrences of `$media->file_path` → `$media->original_path` (hero L142, cards L204, rail L261). Changed `asset('storage/...')` → `Storage::url(...)` for consistency with story-view.blade.php.
- **Tests**: `php artisan test --filter=Story` — 119 passed, 0 failures.
- **Lint**: `php -l` clean on all 3 changed files.
- **Live Smoke**: ⏳ Pending — user should verify on local dev server.
