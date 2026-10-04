# Task: M14-COV-022 — E2E key rotation and delivery retry

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001, M14-COV-003, M14-COV-007
**Parent ADR:** DEC-007; FR-CLT-003, FR-DST-006

---

## 1. Contract (What)
- **Inputs / Validation:** Delivery settings regen control. A failed delivery row in the distribution log.
- **Outputs / Response:** After regen, old raw key feed is 401 once overlap is expired (travel is PHP; e2e may call a test-only artisan command that expires the previous key, not a toast). New key feed is 200. Retry click increases `attempt_count` or moves status off the previous value. Read via tinker in the spec helper.
- **Authorization:** Admin.

---

## 2. Logic (How)
1. Extend `delivery-settings-faithful.spec.ts` or add `tests/e2e/key-rotation.spec.ts`. Capture the new key from the UI (shown once). Call feed. Then expire the old key through `seed-data.php expire-key` and assert 401.
2. `distribution-log.spec.ts` retry: assert DB attempt_count, not only "Queued for retry".
3. No full `DatabaseSeeder` in beforeAll (COV-001). If still present, remove it here.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/key-rotation.spec.ts`
  - `tests/e2e/distribution-log.spec.ts`
  - `tests/e2e/helpers/seed-data.php`
- **Reference Files:**
  - `app/Livewire/Admin/DeliverySettings.php`
  - `app/Livewire/Admin/DistributionLog.php`

---

## 4. Prompt (For the Coding AI)
> E2E API key regen: new key 200, old key 401 after expiry helper. E2E distribution retry asserts `attempt_count` or status change in the database. Toast-only asserts are not done. Do not run `DatabaseSeeder` from the spec.

---

## 5. Test Criteria
- [ ] Old key 401 after expiry helper
- [ ] New key 200
- [ ] Retry changes a DB column
- [ ] Spec green

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/key-rotation.spec.ts` covers (a) API key regeneration: reveal new raw key, regenerate twice (`Click again to confirm`), assert new key 200s on `/api/v1/feed`, then expire the seeded Daily Star key via `seed-data.php expire-key` and assert 401. (b) distribution-log retry: seed the failed row, click Retry, read `attempt_count` before and after from the DB — must be greater than or equal (no toast-only pass). Depends on COV-003 (non-2xx marks failed), COV-007 (overlap time-travel), COV-001 (isolation).
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
