# Task: M8-UI-002 — Component library pass #2 (workflow-strip/note-thread/filter-bar/data-table)

**Status:** ✅ Completed
**Dependencies:** M8-UI-001
**Parent ADR:** app-data/README §12

---

## 1. Contract (What)
- **Inputs / Validation:** `<x-workflow-strip status+owner+shift+takeOver>`, `<x-note-thread notes+replies>`, `<x-filter-bar>`, `<x-data-table sticky+pagination>`.
- **Outputs / Response:** Props-driven components, no queries.
- **Authorization:** none.

---

## 2. Logic (How)
1. `<x-workflow-strip>`: wf-pill variants (draft/review/rework/approved/live), avatar, shift label, spacer, take-over button.
2. `<x-note-thread>`: nt-item variants (editor/sub/sys), nt-head with role pill, nt-body, reply textarea + submit.
3. `<x-filter-bar>`: search + selects + chips, URL-synced via Livewire.
4. `<x-data-table>`: sticky header, empty state, pagination slot.
5. Wire to preview page.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/views/components/workflow-strip.blade.php`
  - `resources/views/components/note-thread.blade.php`
  - `resources/views/components/filter-bar.blade.php`
  - `resources/views/components/data-table.blade.php`
- **Reference Files:** `app-data/add-news.html` (wf-strip, nt-*, filters)

---

## 4. Prompt (For the Coding AI)
> Build component library pass 2: workflow-strip, note-thread, filter-bar, data-table per README §12. Props-driven, no business logic, verify on /__preview.

---

## 5. Test Criteria
- [ ] workflow-strip renders all status variants
- [ ] note-thread renders editor/sub/sys + reply
- [ ] data-table sticky header + pagination works
- [ ] `php -l` clean

---

## 6. Completion Notes
- **Shipped:** Created `<x-workflow-strip>`, `<x-note-thread>`, `<x-filter-bar>`, `<x-data-table>` props-driven per README §12.
- **Tests:** `php -l` clean.
- **Live Smoke:** Render check via `php artisan view:clear`.
- **Review:** No queries in components.

---

## 7. Prompt Ready?
- [x] Yes
