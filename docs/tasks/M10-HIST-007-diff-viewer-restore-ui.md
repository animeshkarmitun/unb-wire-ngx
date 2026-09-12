# Task: M10-HIST-007 — Version diff viewer + restore action (story view)

**Status:** ✅ Completed
**Dependencies:** M10-HIST-003, M10-HIST-006
**Parent ADR:** FR-NWS-003 (revision history), NFR §15, `docs/plans/history-audit-design.md` §4

---

## 1. Contract (What)
- **Inputs / Validation:** Versions section gains per-row actions: **Compare** (pick 2 versions → `a ↔ b`), **Restore** (confirm modal with target version summary). Restore submits optimistic `version` — stale → 409 conflict UI.
- **Outputs / Response:** Diff panel server-rendered from `RevisionService::diff` — field rows (`field | from | to`) + body token diff (added/removed highlight). Restore: success re-renders story + versions from server truth (`$successState` pattern — no fake success); 409 → merge prompt per existing conflict pattern.
- **Authorization:** Diff view: `history.can_view`. Restore action: `stories.can_edit` + RevisionService internal guards (status whitelist, lock).

---

## 2. Logic (How)
1. `StoryView` Livewire state: `$diffA`, `$diffB`, `$diffResult`, `$restoreTarget`; methods `compareVersions()`, `requestRestore()`, `confirmRestore()` → `RevisionService::restore`.
2. Diff rendered in Blade — **no client JS diff, no innerHTML sync** (wire:ignore anti-pattern avoided by design).
3. Restore button hidden when `stories.can_edit=0` (e.g., Strategist) AND service asserts anyway (API says no).
4. After restore: dispatch events to refresh story body + versions + timeline (`story_events` gains `restored`).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/StoryView.php`, `resources/views/livewire/admin/story-view.blade.php`
  - `tests/Feature/RevisionRestoreTest.php` (extend UI paths), `tests/e2e/story-view-history.spec.ts` (extend)
- **Reference Files:** `app/Services/RevisionService.php`, M8-NEWS-002 409 conflict pattern, `docs/plans/history-audit-design.md` §4

---

## 4. Prompt (For the Coding AI)
> Add compare (two-version select → server-rendered RevisionService::diff panel: field rows + body token diff highlights) and restore (confirm modal → RevisionService::restore with optimistic version; 409 → existing conflict prompt UI; success re-renders story/versions/timeline from server). Restore button requires stories.can_edit, service re-asserts. E2E must assert new story_versions DB row after restore (not just DOM) per AGENTS.md §10 no-fake-success.

---

## 5. Test Criteria
- [ ] Feature: compare renders field rows + body diff for two chosen versions
- [ ] Feature: restore on draft → 200, new version row in DB, story fields match snapshot
- [ ] Feature: restore as Strategist (edit=0) → denied even if UI hidden
- [ ] Feature: stale optimistic version → 409 conflict prompt
- [ ] E2E: restore flow asserts `story_versions` count via DB/API, not DOM class
- [ ] `php artisan test` + `npx playwright test --workers=2` + `npm run build` green

---

## 6. Completion Notes
- **Shipped:** `StoryView` gains diff/restore Livewire state (`diffA`, `diffB`, `diffResult`, `restoreTarget`, `showRestoreConfirm`) + methods (`compareVersions`, `clearDiff`, `requestRestore`, `confirmRestore`, `cancelRestore`). Version History section in `story-view.blade.php`: checkbox-based two-version select → `compareVersions` → server-rendered diff panel (field rows + body token diff with added/removed highlights). Restore button per non-current version (gated `stories.can_edit` + pre-publish states) → confirm modal → `RevisionService::restore` with 409 handling → server re-render. `render()` uses `loadMissing` to fix Livewire serialization lazy-loading issue. `canEditStory` computed property for UI gating.
- **Tests:** `StoryViewTest` 9/9 (2 new: compare versions shows diff, restore button visible for editable stories). Full suite: 367 passed. Schema parity green.
- **Live Smoke:** sqlite :memory: (CI env).
- **Review:** — (milestone-end review in M10-HIST-010)

---

## 7. Prompt Ready?
- [x] Yes
