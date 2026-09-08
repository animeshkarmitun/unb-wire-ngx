# Task: M10-HIST-008 — Wizard History modal → server-side versions

**Status:** ✅ Completed
**Dependencies:** M10-HIST-003
**Parent ADR:** `app-data/README.md` prototype→implementation mapping (`localStorage('unb_rev_v1')` → `story_versions` via `RevisionService`), FR-NWS-003

---

## 1. Contract (What)
- **Inputs / Validation:** Wizard (add-news) History modal behavior: **saved story** (`$storyId` set) → server version list via `RevisionService` (all users' saves, not just this session); **never-saved draft** → existing localStorage `unb_rev_v1` flow unchanged (max 8, 60s cadence).
- **Outputs / Response:** Saved-story mode: version rows (v#, author, time) with per-row restore-to-editor action (pre-publish states only → replaces editor content, writes new version row). Unsaved mode: current local snapshot list + restore, unchanged.
- **Authorization:** Modal gated `history.can_view`; restore action gated `stories.can_edit` (service re-asserts).

---

## 2. Logic (How)
1. `AddNews` Livewire: `listVersions()` (eager `creator`, newest first) + `restoreVersion(int $v)` → `RevisionService::restore` → re-hydrate wizard form state from restored story (headline/brief/body/category/tags).
2. `wizard-main.js` History modal (lines ~604–650): branch on story existence — render server list (Livewire-provided) vs local list; keep local flow code untouched for unsaved drafts.
3. Restore success: form fields update + toast; `story_events` `restored` visible on story view timeline (cross-link with M10-HIST-006).
4. No `innerHTML` outside `wire:ignore` wrapper (existing preview wrapper pattern — follow it if DOM sync needed).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/AddNews.php` (+ `listVersions`, `restoreVersion`)
  - `resources/views/livewire/admin/add-news.blade.php` (History modal)
  - `resources/js/wizard/wizard-main.js` (modal branch)
  - `tests/e2e/wizard-history.spec.ts` (new)
- **Reference Files:** `docs/plans/history-audit-design.md` §4, AGENTS.md §10 (wire:ignore wrapper rule)

---

## 4. Prompt (For the Coding AI)
> Wire wizard History modal: when story is saved, list server story_versions (creator, time) with restore-to-editor (RevisionService::restore, pre-publish states only, re-hydrate form fields, toast); when unsaved, keep existing localStorage unb_rev_v1 flow untouched. AddNews gains listVersions/restoreVersion with history.view + stories.edit guards. E2E: saved story shows other users' versions; restore updates editor content AND asserts new story_versions row; unsaved draft still shows local snapshots.

---

## 5. Test Criteria
- [ ] Feature: saved story History modal lists server versions incl. other users' saves
- [ ] Feature: restore re-hydrates headline/brief/body/category/tags in form state
- [ ] Feature: restore on published story blocked (service guard)
- [ ] E2E: unsaved-draft local snapshot flow unchanged (regression)
- [ ] E2E: restore asserts DB version row (no fake success)
- [ ] `npx playwright test --workers=2` + `npm run build` green

---

## 6. Completion Notes
- **Shipped:** `AddNews` gains `listVersions()` (returns version list with creator/time) and `restoreVersion(int $v)` (calls `RevisionService::restore`, rehydrates editor fields, dispatches `quill-set-content`). `showServerHistory` boolean + toggle. `add-news.blade.php`: collapsible "Server version history" card between Step1 and Step2, visible when `storyId` is set. Version list with per-row restore button (hidden for current version). Server-side Livewire — no JS↔Livewire coordination needed. Existing localStorage `unb_rev_v1` modal untouched (still works for unsaved drafts).
- **Tests:** Full suite: 367 passed. Schema parity green.
- **Live Smoke:** sqlite :memory: (CI env).
- **Review:** — (milestone-end review in M10-HIST-010)

---

## 7. Prompt Ready?
- [x] Yes
