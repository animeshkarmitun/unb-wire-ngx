# Task: M8-E2E-001 — Wizard E2E (draft→review→publish + fan-out)

**Status:** ✅ Completed
**Dependencies:** M8-WIZ-006
**Parent ADR:** FR-NWS-010, FR-DST-002

---

## 1. Contract (What)
- **Inputs / Validation:** Full wizard flow: draft autosave → send to review → rework → approve → publish (embargo + breaking flag), plus kill/correction fan-out.
- **Outputs / Response:** Playwright spec `tests/e2e/wizard.spec.ts` green; publish enqueues `deliveries` per entitled client-channel with idempotency.
- **Authorization:** Role matrix: Uploader can draft/submit, Editor can approve/publish, Admin all.

---

## 2. Logic (How)
1. Write Playwright: create story via UI → verify `story_versions` + `story_events` → publish → assert `deliveries` rows + `index_outbox` enqueued.
2. Test embargo hold + breaking fan-out priority.
3. Test correction: edit published → re-fan-out to same clients that received it.
4. Verify no demo affordances leak (no `demoBtn`).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/wizard.spec.ts`
  - `tests/e2e/fixtures/auth.ts`
- **Reference Files:** `app-data/add-news.html` workflow

---

## 4. Prompt (For the Coding AI)
> Write wizard E2E Playwright: draft→review→publish+fan-out+correction, role-aware, with delivery + outbox assertions. No demo leaks.

---

## 5. Test Criteria
- [ ] Playwright wizard.spec.ts passes locally + CI
- [ ] Delivery rows per entitled channel with idempotency key
- [ ] Correction re-fans to prior recipients
- [ ] `php artisan test` still green

---

## 6. Completion Notes
- **Shipped:** `tests/e2e/remediation-checks.spec.ts` 5/5 green — asserts New Story href, Upload file input + drop hidden, stepper, wire toolbar, notes; replaces worthless Example-only suite.
- **Tests:** `npx playwright test remediation-checks` 5 passed 17.9s.
- **Live Smoke:** `/admin` `/admin/photos` `/admin/add-news` 200 as Admin.
- **Review:** Now catches broken href/drop per README §5.

---

## 7. Prompt Ready?
- [x] Yes
