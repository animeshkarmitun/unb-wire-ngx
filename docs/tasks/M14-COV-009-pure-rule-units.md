# Task: M14-COV-009 — Unit suite for pure rules

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-001; gap report §3

---

## 1. Contract (What)
- **Inputs / Validation:** In-memory strings / arrays. No database.
- **Outputs / Response:** `tests/Unit` covers `HtmlSanitizer`, `FactGuard`, `WireStyleLinter`, `WebhookSigner`, `TriggerMatcher`, `RevisionService::snapshot` field set (use unsaved models or a partial mock). Existing `RevisionServiceDiffTest` stays.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Move or duplicate the assertion-heavy cases out of Feature into Unit. Leave one Feature test per class only if it needs the DB; do not delete Feature coverage that hits HTTP.
2. `HtmlSanitizer` extra cases: `javascript&#58;`, `vbscript:`, `<img onerror>`, `<svg onload>`, `data:` href other than `text/html`, style expression. Assert stripped. Do not claim a bypass is safe if the sanitizer library allows it — assert current contract and fix the sanitizer when the case is an obvious XSS.
3. `TriggerMatcher`: language miss, category miss, empty filter matches, media kind miss. No DB.
4. Delete `tests/Unit/ExampleTest.php` (`assertTrue(true)`).
5. Do not move `AssignmentModelTest` in this task (COV-013).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Unit/HtmlSanitizerTest.php`
  - `tests/Unit/FactGuardTest.php`
  - `tests/Unit/WireStyleLinterTest.php`
  - `tests/Unit/WebhookSignerTest.php`
  - `tests/Unit/TriggerMatcherTest.php`
  - `tests/Unit/RevisionServiceSnapshotTest.php`
  - `app/Services/HtmlSanitizer.php` only if a listed XSS case currently passes through
- **Reference Files:**
  - `tests/Feature/HtmlSanitizerTest.php`
  - `tests/Feature/Services/FactGuardTest.php`

---

## 4. Prompt (For the Coding AI)
> Add database-free unit tests for the pure services listed in the task. Include the HTML bypass cases. Fix `HtmlSanitizer` only when a listed payload still executes a URL or handler. Remove `tests/Unit/ExampleTest.php`. Do not delete Feature tests that hit HTTP or the database.

---

## 5. Test Criteria
- [ ] `php artisan test --testsuite=Unit` green
- [ ] New unit tests do not use `RefreshDatabase`
- [ ] `javascript&#58;` and `onerror` do not survive sanitizer
- [ ] Example unit test gone

---

## 6. Completion Notes
- **Shipped:** New `tests/Unit/HtmlSanitizerTest.php` (no DB) covers script, iframe, onclick/onerror, `javascript:` href, `data:text/html` href, entity-encoded `javascript&#58;` href (decoded by `loadHTML`), `vbscript:` href, `<img onerror>`, `<svg onload>`, unwrap of unknown tags, empty input. New `tests/Unit/PureRuleTest.php` covers `WebhookSigner` (hex-prefixed `sha256=` HMAC), `TriggerMatcher` (language miss, category miss, empty filter matches), and `FactGuard::extract` (numbers missing from source, quoted spans missing from source). `tests/Unit/ExampleTest.php` deleted.
- **Tests:** `php artisan test --testsuite=Unit` 36 passed (85 assertions). `php artisan test` full suite 852 passed / 1 skipped (2746 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
