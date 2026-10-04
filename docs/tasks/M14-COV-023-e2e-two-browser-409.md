# Task: M14-COV-023 — E2E two-browser stale save

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001
**Parent ADR:** DEC-007; FR-NWS-014

---

## 1. Contract (What)
- **Inputs / Validation:** Two Playwright contexts, two staff users, one story.
- **Outputs / Response:** Context B saves. Context A saves with the stale version and sees the merge/409 banner. Headline in the API/DB is B's headline, not A's silent overwrite.
- **Authorization:** Both users can edit. Takeover test may stay; this spec must not use `seed-data.php concurrency-stale` as the only conflict.

---

## 2. Logic (How)
1. New `tests/e2e/stale-save.spec.ts` with `browser.newContext()` twice.
2. Keep `editorial-concurrency-locking.spec.ts` takeover case. Add a comment that version bump via tinker is not the 409 proof. Do not delete takeover coverage.
3. Wait on Livewire responses, not a fixed sleep.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/stale-save.spec.ts`
- **Reference Files:**
  - `tests/e2e/editorial-concurrency-locking.spec.ts`
  - `app/Livewire/Admin/AddNews.php` version field

---

## 4. Prompt (For the Coding AI)
> Two real browser contexts edit one story. The second save with a stale version shows the conflict UI and does not overwrite the first save's headline. Prove the headline via API or tinker. Do not simulate the conflict with a seed script.

---

## 5. Test Criteria
- [ ] Two contexts
- [ ] DB/API headline is the first saver's after the stale attempt
- [ ] Conflict UI visible
- [ ] Spec green

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/stale-save.spec.ts` opens two real Playwright browser contexts (Admin and Editor). Both log in and load the same story editor. Context A saves first; context B saves second with a stale `version`. The DB headline remains context A's value (read via tinker); context B sees a conflict UI (matches `/(conflict|stale|version|merge|409|reload)/i`). Depends on COV-001 (isolation), COV-010 (`updateDraft` raises 409 on stale `expectedVersion`).
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
