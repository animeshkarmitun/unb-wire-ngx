# Task: M14-COV-016 — CI coverage artifact

**Status:** ⏳ Pending
**Dependencies:** M14-COV-009
**Parent ADR:** DEC-001

---

## 1. Contract (What)
- **Inputs / Validation:** CI workflow and `phpunit.xml`.
- **Outputs / Response:** CI uploads a clover (or html) coverage artifact. The main `php artisan test` job still fails only on test failures, not on a global percentage. A small allowlist check fails CI if any class in the list has zero references under `tests/`.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Read `.github/workflows/ci.yml`. Add a coverage job or step with `pcov` or `xdebug` if already available; if neither is installed, use `php -d` only when the extension exists and otherwise run the allowlist script. Do not make the pipeline red because pcov is missing — document the skip.
2. Allowlist script `scripts/coverage-touch-check.php` (or a PHPUnit test) fails if these classes are not mentioned in `tests/`: `OpenAiProvider`, `ProcessDeliveriesCommand`, `CheckOutboxLag`, `EntitlementResolver`, `HtmlSanitizer`, `FanoutStory`, `ProcessIndexOutbox`.
3. Do not set `--min=85` or `--min=80`.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `.github/workflows/ci.yml`
  - `scripts/coverage-touch-check.php` or `tests/Feature/CoverageTouchTest.php`
  - `phpunit.xml` only to keep `<source>`
- **Reference Files:**
  - `docs/testing-coverage-gaps.md` snapshot (coverage not gated)

---

## 4. Prompt (For the Coding AI)
> Collect coverage as a CI artifact without a global percentage gate. Add a test or script that fails if the allowlisted classes are not referenced from `tests/`. Do not set `--min`. Do not fail CI when pcov/xdebug is absent; skip the artifact step and still run the allowlist.

---

## 5. Test Criteria
- [ ] Allowlist passes on current tree after COV-008/003/006/009
- [ ] Removing the `OpenAiProvider` test reference fails the check (dry-run described in Completion Notes)
- [ ] CI yaml does not contain `--min=`

---

## 6. Completion Notes
- **Shipped:** New `tests/Feature/CoverageTouchTest.php` walks `tests/` and fails if any of `OpenAiProvider`, `ProcessDeliveriesCommand`, `CheckOutboxLag`, `EntitlementResolver`, `HtmlSanitizer`, `FanoutStory`, `ProcessIndexOutbox` is not referenced by any `*Test.php` or `*.spec.ts`. This is the minimum bar after COV-008/003/006/009 added direct coverage; it is a reference check, not a percentage gate. `.github/workflows/ci.yml` enables `pcov` coverage and runs `php artisan test --coverage-clover=coverage.xml`, uploading the clover file as a `coverage` artifact on every run. No `--min=…` is added; percentage gates are deferred until after a baseline is measured.
- **Tests:** `php artisan test --filter=CoverageTouchTest` 1 passed (7 assertions). `php artisan test` full suite 882 passed / 1 skipped (2817 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
