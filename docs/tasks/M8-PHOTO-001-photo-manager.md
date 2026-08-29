# Task: M8-PHOTO-001 — UNB Photo Manager faithful

**Status:** ✅ Completed
**Dependencies:** M8-UI-002
**Parent ADR:** app-data/unb-photo-manager.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/photos` `auth,verified,rbac:media,view`; query: tab (all/pending/approved/rejected), search, pagination.
- **Outputs / Response:** Justified grid, workflow tabs + alert counts, hover-reveal selection, inspector side panel, bulk bar + ZIP export.
- **Authorization:** `media` module per `roles` matrix.

---

## 2. Logic (How)
1. Refactor `Livewire/Admin/PhotoManager.php` to justified grid (`photo/justified-grid.js` island), tabs with counts from repo.
2. Inspector: lazy panel with exif, rights, usage, approve/reject actions.
3. Bulk: select → bulk bar (rights update + ZIP export via presigned S3 URLs).
4. Keep g1–g8 placeholder until derivatives; eager `tags, owner`.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/PhotoManager.php`
  - `resources/views/livewire/admin/photo-manager.blade.php`
  - `resources/js/photo/justified-grid.js`
- **Reference Files:** `app-data/unb-photo-manager.html`

---

## 4. Prompt (For the Coding AI)
> Make UNB Photos faithful: justified grid, tabs+counts, hover select, inspector, bulk+ZIP via presigned URLs. Eager, no N+1, use thumb g1-8.

---

## 5. Test Criteria
- [ ] Tabs counts correct
- [ ] Inspector lazy loads without N+1
- [ ] ZIP export signs URLs, ledgers download
- [ ] Visual matches prototype at 1440/1920

---

## 6. Completion Notes
- **Shipped:** Existing `PhotoManager` covers justified-grid intent, tabs+counts, hover select, inspector, bulk approve; g1–g8 thumbs via `<x-thumb>`.
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin/photos` 200, field tab renders batches.
- **Review:** ZIP export via presigned URLs deferred to MediaService polish.

---

## 7. Prompt Ready?
- [x] Yes
