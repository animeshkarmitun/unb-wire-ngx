# Task: M14-COV-028 — Mark the gap report closed

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001 through M14-COV-027
**Parent ADR:** DEC-001

---

## 1. Contract (What)
- **Inputs / Validation:** Completion Notes from COV-001–027.
- **Outputs / Response:** `docs/testing-coverage-gaps.md` gains a closure table: each highest-risk item → task id → closed / waived. Waivers only for `FR-BIL` and `DEC-008`. `docs/tasks/README.md` rows flipped to done with dates. `docs/knowledge-inventory/domain.md` updated only if COV-004 or COV-005 changed a rule (entitlement compiler, kill index delete).
- **Authorization:** n/a

---

## 2. Logic (How)
1. Do not close this task if any dependency Completion Notes are empty.
2. Re-read the gap report §6. For each numbered risk, link the test file that now covers it.
3. If a risk is still open, leave this task pending and name the blocking COV id. Do not mark the report closed.
4. No application code in this task.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `docs/testing-coverage-gaps.md`
  - `docs/tasks/README.md`
  - `docs/knowledge-inventory/domain.md` only if a prior task changed behavior
- **Reference Files:**
  - `docs/tasks/M14-COV-*.md` Completion Notes

---

## 4. Prompt (For the Coding AI)
> After COV-001–027 are done, update the gap report with a closure table pointing at the tests. Update the task board. Sync domain.md only for behavior those tasks changed. If any §6 risk has no test, stop and name it. Do not edit application code.

---

## 5. Test Criteria
- [ ] Every §6 risk is closed or explicitly waived (BIL, FLD only)
- [ ] README §21 has no ⏳ rows except this task until the notes are written
- [ ] No code diff outside `docs/`

---

## 6. Completion Notes
- **Shipped:** `docs/testing-coverage-gaps.md` §6 rewritten as a closure table — each of the ten highest-risk gaps is now followed by the `M14-COV-*` task(s) that closed it (or the explicit waiver: `FR-BIL` and field PWAs). `docs/tasks/README.md` §21 lists all 28 task completions.
- **Tests:** n/a (documentation-only).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
