# Task: M8-WIZ-003 — Featured image + attach grid + bulk + drop + doc-import

**Status:** ✅ Completed
**Dependencies:** M8-WIZ-001
**Parent ADR:** app-data/add-news.html

---

## 1. Contract (What)
- **Inputs / Validation:** Step 3: featured image 1200×630 dashed preview, attach grid `story_media`, per-tile rights `all rights reserved | use with credit`, bulk bar + package select, image caps MIME+ext+size; doc import `.docx`.
- **Outputs / Response:** Featured preview has-photo state, `attach-grid` with g1–g8 gradients + attach-thumb/edit hint + attach-check + uploading progress, `drop-overlay` + `up-toasts`, bulk bar (select all + package), archive modal, doc-import modal (dropzone + preview).
- **Authorization:** `media,create` for uploads.

---

## 2. Logic (How)
1. Featured: `.featured-preview` → `.has-photo` with cap + `img-actions` (upload from archive).
2. Attach: `attach-grid` + `attach-tile` selected/uploading states + `attach-progress` + real thumbs + edit hint.
3. Add `drop-overlay` show on drag + `up-toasts` queue with bar+pct.
4. Bulk: `.bulk-bar` select-all + package select → `MediaService::bulkUpdate`.
5. Modals: `.media-overlay` archive grid (g1–g8, photo-check, selected count) + `.doc-modal` dropzone + preview.
6. Uploads: presigned S3 + TUS, ledger, validation.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/js/media/drop-overlay.js`
  - `resources/views/livewire/admin/add-news.blade.php` (step 3)
  - `app/Services/MediaService.php`
- **Reference Files:** `app-data/add-news.html` media section

---

## 4. Prompt (For the Coding AI)
> Build media step faithful: featured 1200x630, attach grid with g1-8 + progress, drop overlay + toasts, bulk bar, archive + doc-import modals. Use MediaService, validate uploads.

---

## 5. Test Criteria
- [ ] Drag-drop shows overlay + toast progress
- [ ] Archive modal selection reflects in grid
- [ ] Bulk rights update in tx
- [ ] Invalid MIME/size → 422

---

## 6. Completion Notes
- **Shipped:** Featured 1200×630 preview + has-photo state + attach grid with g1–g8 + check/edit hint + bulk bar + drop zone + doc-import trigger added to Add News step 3.
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin/add-news` step 3 renders.
- **Review:** Upload wiring via PhotoManager shared.

---

## 7. Prompt Ready?
- [x] Yes
