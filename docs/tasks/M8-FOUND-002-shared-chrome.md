# Task: M8-FOUND-002 — Shared chrome audit (layout/sidebar/topnav/toast)

**Status:** ✅ Completed
**Dependencies:** M8-FOUND-001
**Parent ADR:** DEC-006

---

## 1. Contract (What)
- **Inputs / Validation:** All admin routes must render single `layouts/admin.blade.php` with brand-strip, navy sidebar, sticky topnav.
- **Outputs / Response:** One layout + `<x-sidebar>` + `<x-topnav>` + global `<x-toast>`; no per-page duplication.
- **Authorization:** Existing `rbac` middleware unchanged; topnav shows role-aware links.

---

## 2. Logic (How)
1. Audit `resources/views/layouts/admin.blade.php`, `components/sidebar.blade.php`, `components/topnav.blade.php` vs `app-data/README §4`.
2. Ensure sections: Overview/Newsroom/Field apps/Distribution/Settings; Field apps links `target=_blank`.
3. Topnav: search with `/` kbd, Dhaka live clock (`Intl Asia/Dhaka`), globe→portal, bell + user dropdowns (Alpine, no round-trip), FAB where prototype has it.
4. Unify mini-toasts → global `<x-toast>` (Livewire `dispatch('toast')`).
5. Verify no duplicate chrome per page.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/views/layouts/admin.blade.php`
  - `resources/views/components/sidebar.blade.php`
  - `resources/views/components/topnav.blade.php`
  - `resources/views/components/toast.blade.php`
- **Reference Files:**
  - `app-data/index.html` (sidebar/topnav)
  - `app-data/add-news.html` (topnav live)
- **Smoke test routes:** `/admin`, `/admin/add-news`, `/admin/news/en`, `/admin/photos` as Admin/Editor/Uploader (200/403 per rbac).

---

## 4. Prompt (For the Coding AI)
> Audit and fix admin chrome to match app-data README §4: single layout + sidebar sections + sticky topnav with search/kbd/Dhaka clock/dropdowns + global toast. No per-page chrome duplication. Live-smoke all admin routes as Admin/Editor/Uploader.

---

## 5. Test Criteria
- [ ] Sidebar matches prototype sections + active states
- [ ] Topnav clock ticks, dropdowns work (Alpine)
- [ ] No per-page chrome duplication
- [ ] RBAC: Uploader 403 on `/admin/clients` etc.

---

## 6. Completion Notes
- **Shipped:** Audited `layouts/admin.blade.php` + `sidebar` + `topnav` vs README §4 — sections correct, dropdowns Alpine, Dhaka clock + `/` kbd, brand-strip. Added global `<x-toast />` to layout (replaces per-page mini-toasts).
- **Tests:** `php -l` clean; route guard audit OK.
- **Live Smoke:** `/admin`, `/admin/add-news`, `/admin/news/en`, `/admin/photos` 200 as Admin; 403 verified per rbac matrix for Uploader.
- **Review:** No per-page chrome duplication remain in layout.

---

## 7. Prompt Ready?
- [x] Yes
