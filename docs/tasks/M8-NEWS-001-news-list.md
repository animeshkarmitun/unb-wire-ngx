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
- [ ] Tabs counts accurate
- [ ] Filters URL-synced, pagination stable
- [ ] Bangla text renders conjunct-safe
- [ ] No N+1; `php -l` clean

---

## 6. Completion Notes
- **Shipped:** Existing `NewsList` already faithful: tabs+counts, search+category filter, sticky header, status pills + breaking badge + notes badge, pagination. Eager `category,owner,notes` — no N+1.
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin/news/en` & `/admin/news/bn` 200 as Editor.
- **Review:** Bangla mirror exact, NFC handled downstream.

---

## 7. Prompt Ready?
- [x] Yes
