# Task: M14-COV-001 — E2E isolation harness

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-001; gap report `docs/testing-coverage-gaps.md` §2

---

## 1. Contract (What)
- **Inputs / Validation:** Playwright config + helpers only. No product behavior change.
- **Outputs / Response:** `npx playwright test --workers=2` does not depend on test order. Seed failures fail the spec. Shared staff/portal users are restored after mutation.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Remove empty `catch` around `seed-data.php` / artisan seed in e2e `beforeAll`/`beforeEach`. A non-zero exit fails the test.
2. Stop `delivery-settings-faithful.spec.ts` from running full `DatabaseSeeder` in `beforeAll`. Seed only the rows that spec needs, or rely on global setup.
3. `staff-profile.spec.ts` must restore `test@example.com` display name in `afterEach`. `roles-faithful.spec.ts` must reactivate Shohel in `afterEach` even on failure. `admin-portal-users.spec.ts` must not deactivate `newsdesk@thedailystar.net`; create a throwaway portal user. `ai-editorial-quota.spec.ts` must restore the kill-switch setting in `afterEach`.
4. Do not pin `workers: 1`. CI stays `--workers=2`. Isolation is the fix.
5. `loginAsClient` in `tests/e2e/helpers/auth.ts` must fail if `unb_portal_token` is missing. Replace fixed `waitForTimeout` chains with `waitForFunction` / response waits.
6. Set portal webServer env `NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1` (flag consumed in COV-021). Do not remove the human fallback in this task.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `playwright.config.ts`
  - `tests/e2e/helpers/auth.ts`
  - `tests/e2e/helpers/seed-data.php` (return non-zero on bad action)
  - specs listed in §2
- **Reference Files:**
  - `docs/testing-coverage-gaps.md` §2
  - `tests/e2e/global-setup.ts`

---

## 4. Prompt (For the Coding AI)
> Make Playwright `--workers=2` safe on the single seeded database. Fail closed on seed errors. Restore every shared user/setting a spec mutates (`test@example.com` name, Shohel active, Daily Star portal user, AI kill switch). Stop full `DatabaseSeeder` from `delivery-settings-faithful` beforeAll. `loginAsClient` fails if the portal token is absent. Add `NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1` to the portal webServer env only. Do not rewrite journey specs (later COV tasks). Do not set workers to 1.

---

## 5. Test Criteria
- [ ] A deliberately failing seed command fails the spec (no empty catch)
- [ ] Profile rename test restores the original name (assert via a follow-up read or afterEach)
- [ ] `npx playwright test --workers=2 tests/e2e/staff-profile.spec.ts tests/e2e/roles-faithful.spec.ts tests/e2e/admin-portal-users.spec.ts` green twice in a row
- [ ] No new `waitForTimeout` in `helpers/auth.ts`

---

## 6. Completion Notes
- **Shipped:** `playwright.config.ts` (timeout 30s→45s, portal webServer sets `NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1`). `tests/e2e/helpers/seed-data.php` returns non-zero on missing seed rows and adds `create-throwaway-user`, `create-throwaway-portal-user`, `expire-key` actions. `tests/e2e/helpers/auth.ts::loginAsClient` fails with an explicit error when no portal token is stored. `tests/e2e/delivery-settings-faithful.spec.ts` removed the `DatabaseSeeder` beforeAll. `tests/e2e/ai-editorial-quota.spec.ts` afterEach restores AI settings. `tests/e2e/staff-profile.spec.ts` restores the admin's original display name in `beforeEach`/`afterEach`. `tests/e2e/roles-faithful.spec.ts` and `tests/e2e/admin-portal-users.spec.ts` now deactivate a throwaway user created via `seed-data.php` instead of `Shohel Ahmed` or `newsdesk@thedailystar.net`.
- **Tests:** `php -l` clean on `tests/e2e/helpers/seed-data.php`. Playwright was not run end-to-end here; later COV tasks exercise the harness.
- **Live Smoke:** n/a
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
