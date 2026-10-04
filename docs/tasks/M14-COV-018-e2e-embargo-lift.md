# Task: M14-COV-018 — E2E embargo then lift

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001, M14-COV-002
**Parent ADR:** DEC-007; domain.md §2.5 (FR-NWS-007)

---

## 1. Contract (What)
- **Inputs / Validation:** Wizard sets `embargo_until` 2 minutes ahead in Asia/Dhaka. Story reaches `approved` (or the state the worker selects — today `approved`).
- **Outputs / Response:** Before the worker runs, `/api/v1/portal/feed` and the client feed do not contain the headline. After the worker (invoke the same invokable COV-002 extracted, via `php artisan` if a command exists, or tinker calling that class — not a raw SQL update), the feed contains it.
- **Authorization:** Admin session.

---

## 2. Logic (How)
1. New `tests/e2e/embargo-lift.spec.ts`.
2. Do not `UPDATE stories SET status=published` from the spec. Call the lift entrypoint COV-002 created.
3. Assert absence before and presence after. Also assert a still-future embargo stays off the feed when the worker runs.
4. Checkbox "notify embargoed" toast in delivery settings is not this test.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/embargo-lift.spec.ts`
- **Reference Files:**
  - lift invokable from COV-002
  - `app/Livewire/Admin/AddNews.php` embargo field

---

## 4. Prompt (For the Coding AI)
> Add a Playwright spec that publishes under a future embargo, proves the headline is absent from the portal feed, runs the real lift entrypoint, then proves the headline is present. A second story with a future embargo stays absent. No raw SQL status update.

---

## 5. Test Criteria
- [ ] Absence assert before lift
- [ ] Presence assert after lift
- [ ] Future embargo still absent
- [ ] Spec green under `--workers=2`

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/embargo-lift.spec.ts` drives the embargo→lift journey end-to-end: Admin sets a future `embargo_until` (~2 min from now in Asia/Dhaka) via the wizard and clicks publish. The portal feed does **not** contain the unique headline (embargo active). A tinker call advances the embargo into the past and runs the real `LiftEmbargoedStories` invokable — no raw `UPDATE stories SET status=published`. The feed then contains the headline. A second story with a future embargo is published; after another lift run, it remains absent from the feed. Depends on COV-002 (the invokable) and COV-001 (isolation harness).
- **Tests:** not run in this environment (Playwright needs a live server).
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
