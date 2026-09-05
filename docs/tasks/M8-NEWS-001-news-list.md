# Task: M8-NEWS-001 — News list faithful (en + bn)

**Status:** ✅ Completed
**Dependencies:** M8-UI-002
**Parent ADR:** app-data/english-news.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/news/{language}` where language `en|bn`; filters: status, category, search, pagination (cursor).
- **Outputs / Response:** Livewire `NewsList` with wf-tabs + counts, filter bar, sticky data-table, row states (In review/Needs work) + owner line + notes badge.
- **Authorization:** `rbac:stories,view`; Bangla mirror exact.

---

## 2. Logic (How)
1. Refactor `Livewire/Admin/NewsList.php` + blade to use `<x-tabs>` + `<x-filter-bar>` + `<x-data-table>`.
2. Repository query: partition-aware, eager `category, owner, notesCount`, cursor pagination, tag invalidation.
3. Add status pills, owner+shift line, notes badge; empty states.
4. Ensure `language` scoping toggles `stories.language`; Bangla uses Bangla font + NFC.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/NewsList.php`
  - `resources/views/livewire/admin/news-list.blade.php`
  - `app/Repositories/StoryRepository.php`
- **Reference Files:** `app-data/english-news.html`

---

## 4. Prompt (For the Coding AI)
> Make news list faithful to english-news.html: tabs with counts, filter bar, sticky table, row workflow states + owner+notes badge, cursor pagination, language-scoped. Enforce eager loading.

---

## 5. Test Criteria
- [x] Tabs counts accurate
- [x] Filters URL-synced, pagination stable
- [x] Bangla text renders conjunct-safe
- [x] No N+1; `php -l` clean

---

## 6. Completion Notes
- **Shipped:** 1:1 conversion of English & Bangla news desks from `app-data/english-news.html`:
  - Full 7-column table with bulk select checkboxes, thumbnail tints (`.t-blue`, `.t-green`, `.t-purple`, `.t-amber`), headline, owner line with avatar initials (`.owner-line .wf-ava`), contextual review status, and notes badge.
  - Category badges with color styling (`.cat-tag.bangladesh`, `.sports`, `.world`, `.business`).
  - Sub category column (`.subcat`) and tabular views column with eye icon (`.views`).
  - Live toggle switches (`.switch .slider`) for published/draft states and workflow pills for in-review/changes-requested states.
  - Row action buttons (`.actions .icon-btn`) for Edit and Delete with RBAC checks.
  - Filter bar with debounced search, category dropdown, status dropdown, and Search button.
  - Bulk action toolbar with Bulk Publish and Bulk Delete for selected items.
  - Topbar Export button providing streaming CSV download of filtered stories.
  - Custom pagination block with page buttons (`.news-page-btn`) and results info summary.
- **Tests:** `php -l` clean, `tests/Feature/NewsListTest.php` (6 tests, 26 assertions) PASS, `tests/e2e/news-list-faithful.spec.ts` (3 tests) PASS.
- **Live Smoke:** `/admin/news/en` & `/admin/news/bn` 200 as Admin and Editor.
- **Review:** Zero N+1 queries with eager loaded relations. Bangla font and scoping verified.

---

## 7. Prompt Ready?
- [x] Yes
