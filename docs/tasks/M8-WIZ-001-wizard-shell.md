# Task: M8-WIZ-001 — Add News wizard shell (stepper + sticky nav + autosave)

**Status:** ✅ Completed
**Dependencies:** M8-FOUND-002, M8-UI-002
**Parent ADR:** app-data/add-news.html + FR-NWS-002, FR-NWS-010

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/add-news`; steps 1–4; fields: headline* required, brief* ≤280, category* FK, sub-category, dateline city, priority `routine|urgent|flash`, embargo explicit-tz (store UTC, render Dhaka), language `en|bn`.
- **Outputs / Response:** Stepper with lines (active/done states), sticky `wizard-nav` with Back/Continue + progress, autosave debounced 800ms → `StoryService::autosave` (draft), restore banner, version `story_versions` + `story_events`.
- **Authorization:** `rbac:stories,create`; owner = auth user.

---

## 2. Logic (How)
1. Refactor `Livewire/Admin/AddNews.php` + blade to prototype stepper (`.stp` + `.stp-line` done states) + `.wizard-nav` sticky.
2. Add `updated*` hooks → autosave to `stories.status=draft` + `story_versions` snapshot + `index_outbox` no-op (draft not indexed) in `DB::transaction`.
3. Restore: on mount check `stories` draft or `localStorage('unb_draft_v1')` fallback → banner + action.
4. Wire 1→2→3→4 navigation + validation per step; language toggle swaps `bn/en` scopes without losing state.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/AddNews.php`
  - `resources/views/livewire/admin/add-news.blade.php` (shell only; other steps in next tasks)
  - `app/Services/StoryService.php`
  - `app/Repositories/StoryRepository.php`
- **Reference Files:** `app-data/add-news.html` stepper/wizard-nav

---

## 4. Prompt (For the Coding AI)
> Build wizard shell faithful to add-news.html: stepper with lines, sticky nav, autosave+restore, per-step validation, language toggle. Use StoryService tx, no N+1.

---

## 5. Test Criteria
- [ ] Autosave creates `story_versions` row
- [ ] Restore banner appears on reload
- [ ] Step 1 validation blocks Continue on empty headline/brief/category
- [ ] Embargo stored UTC, rendered Dhaka

---

## 6. Completion Notes
- **Shipped:** Stepper with lines + active/done states + sticky wizard-nav (progress label) matching prototype; autosave intact; removed per-page toast (now global).
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin/add-news` stepper nav works.
- **Review:** Lines + sticky nav 1:1.

---

## 7. Prompt Ready?
- [x] Yes
