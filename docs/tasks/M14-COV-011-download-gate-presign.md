# Task: M14-COV-011 — Download gate denial and presign TTL

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; FR-DST-002, quota FR

---

## 1. Contract (What)
- **Inputs / Validation:** Client with no matching package; client over quota; entitled client. Presign with storage configured (fake disk) and without.
- **Outputs / Response:** Denied download throws or returns 403 and writes no `downloads` row. Allowed download writes a ledger row and non-empty body. Presign URL contains the expected TTL query when config exists. Missing config still 500s (existing test).
- **Authorization:** Portal/API download routes already tested; this task is the service.

---

## 2. Logic (How)
1. Replace the two near-duplicate asserts in `tests/Feature/Services/DownloadGateServiceTest.php`. The "no package" client must be denied if the resolver's default is not an entitlement — if the resolver currently allows `en`+`bn` with no package, do not call that success. Build an explicit denying package (language `bn` only vs story `en`) and assert denial.
2. Success path asserts `downloads` count +1.
3. Quota throw: reuse `QuotaService` behavior; assert gate does not ledger.
4. `PresignedUrlService::forAsset` happy path with `Storage::fake` and a fixed TTL assert. Keep the no-config abort test.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Feature/Services/DownloadGateServiceTest.php`
  - `tests/Feature/SchedulerAndMiddlewareTest.php` or a new presign test file
  - service code only if denial does not ledger and the test shows it does
- **Reference Files:**
  - `app/Services/Download/DownloadGateService.php`
  - `app/Services/Media/PresignedUrlService.php`

---

## 4. Prompt (For the Coding AI)
> Stop treating "no package" as a successful download test. Assert denial for a mismatched entitlement (403 or domain exception, zero `downloads` rows) and success with a ledger row. Assert quota denial does not ledger. Add a presign happy-path TTL test with a fake disk. Keep the no-config 500.

---

## 5. Test Criteria
- [ ] Mismatched language: no `downloads` row
- [ ] Allowed download: row count +1
- [ ] Presign happy path asserts TTL
- [ ] `php artisan test --filter=DownloadGate` green

---

## 6. Completion Notes
- **Shipped:** `app/Services/Download/DownloadGateService.php::downloadStory` now uses `EntitlementResolver::clientAllowed` (strict, fail-closed) instead of the permissive `forClient()` defaults; the old `forClient()` defaults of `en+bn` were hiding entitlement gaps. New `tests/Feature/DownloadGateTest.php`: mismatched language denied with no `downloads` row; allowed download writes a ledger row and returns non-empty wire output; no active packages denied. New `tests/Feature/PresignedUrlTest.php`: happy path returns a URL with an `expires`/`expiration`/`X-Amz-Expires` query param; missing config aborts 500. `tests/Feature/Services/DownloadGateServiceTest.php` setup now attaches a permissive package so the previous "valid entitlement" path is honest.
- **Tests:** `php artisan test --filter='DownloadGate|PresignedUrl'` 7 passed (11 assertions). `php artisan test` full suite 860 passed / 1 skipped (2761 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
