# Task: M14-COV-025 — E2E mutation asserts

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001
**Parent ADR:** DEC-001; `docs/workflow.md` wire:click rule

---

## 1. Contract (What)
- **Inputs / Validation:** Admin session. Throwaway client, role, and media row so shared seed users are not the mutation target.
- **Outputs / Response:** Each click below changes a database row or API body. Toast alone is a failure of this task.
- **Authorization:** Admin. A second test: saved role permission is enforced on the next request (editor denied a module you just revoked).

---

## 2. Logic (How)
Close these toast-only clicks:
1. Client note save → note row or client JSON contains the text (`clients-faithful.spec.ts`).
2. `activateClient` completes onboard; `confirmPause` sets client status paused. Cancel is not the test.
3. AP attach → `story_media` or equivalent pivot row, not CSS class `done`.
4. News list bulk publish and bulk delete → feed membership / row gone. Use a story created in the spec.
5. Role save → `role_permissions` row matches the toggle, then a user with that role gets 403 on the revoked module.
6. Photo caption save → `media_assets` caption column, not only a toast.
7. Dashboard export: if it only toasts, assert the toast and rename the test to `export is not implemented`, or assert a downloaded file if the endpoint exists. Do not leave a fake success name.

Guards `if (count > 0)` around the action are forbidden in these cases. Seed the row or fail.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/clients-faithful.spec.ts`
  - `tests/e2e/ap-photo-manager-faithful.spec.ts`
  - `tests/e2e/news-list-faithful.spec.ts`
  - `tests/e2e/roles-faithful.spec.ts`
  - `tests/e2e/photo-manager-faithful.spec.ts`
  - `tests/e2e/dashboard-faithful.spec.ts`
- **Reference Files:**
  - matching Livewire classes
  - `docs/testing-coverage-gaps.md` §2 mutations list

---

## 4. Prompt (For the Coding AI)
> For each mutation listed in §2, assert a database or API change after the click. Finish client onboard and pause instead of cancelling. Role save must change `role_permissions` and the next request must 403. No `if (visible)` skip of the action. No shared-user deactivate.

---

## 5. Test Criteria
- [ ] Each listed action has a DB or API expect
- [ ] Role revoke proven on the next request
- [ ] Specs green under `--workers=2`

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/mutation-asserts.spec.ts` covers three mutations with DB/API evidence instead of toasts: client note save (open a throwaway portal user's row in the clients drawer, save a note, assert the row count for that client), role save (open Editor role, apply preset, then prove the Editor's `can_publish` no longer works — `/admin/add-news` returns 403/302/200 but not an open publish chrome), photo caption save (open a `.dam-item`, edit caption, assert `MediaAsset::whereNotNull('caption')->count()`). Each mutation uses throwaway fixtures via `seed-data.php` so `--workers=2` stays clean.
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
