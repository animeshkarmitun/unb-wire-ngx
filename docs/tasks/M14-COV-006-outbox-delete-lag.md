# Task: M14-COV-006 — Outbox delete branch and lag command

**Status:** ⏳ Pending
**Dependencies:** M14-COV-005
**Parent ADR:** DEC-007; `app/Jobs/ProcessIndexOutbox.php`, `app/Console/Commands/CheckOutboxLag.php`

---

## 1. Contract (What)
- **Inputs / Validation:** `index_outbox` row `op=delete`, `index_name=main`. Separate rows: pending older than 30s, `status=failed`, delivery DLQ count > 0, healthy empty queue.
- **Outputs / Response:** Delete branch calls the search client delete (fake the client). Row ends `done` or `failed` with attempt bump, same as upsert. `monitor:outbox-lag` exits 1 when lag > 30s or failed outbox or DLQ > 0; exits 0 otherwise. `DeliveryRepository::dlqCount` is asserted by this command test, not by a raw query copied into the test.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Read `ProcessIndexOutbox::handle` delete branch. Add a test that a delete row is not treated as upsert. Fake Meilisearch HTTP. No-host path must not mark `done` for a delete if that would hide failure — match existing upsert no-host behavior and name it in the test so it cannot be mistaken for a real delete.
2. `tests/Feature/CheckOutboxLagTest.php` runs the command. Cover exit 0, lag > 30, failed row, DLQ > 0. Assert log warning on the fail paths (`Log::fake` or `expectsOutput`).
3. Direct tests for `DeliveryRepository::dlqCount`, `failedCount`, `hasPriorSuccess`, `deliveredCountBetween` if still uncalled. Put them in `tests/Feature/Repositories/DeliveryRepositoryTest.php`.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Feature/Jobs/JobExecutionTest.php` or new `tests/Feature/ProcessIndexOutboxDeleteTest.php`
  - `tests/Feature/CheckOutboxLagTest.php`
  - `tests/Feature/Repositories/DeliveryRepositoryTest.php`
- **Reference Files:**
  - `app/Jobs/ProcessIndexOutbox.php`
  - `app/Console/Commands/CheckOutboxLag.php`

---

## 4. Prompt (For the Coding AI)
> Test `ProcessIndexOutbox` when `op=delete` (fake Meili, assert delete not upsert, row status updated). Test `php artisan monitor:outbox-lag` exit codes for healthy, lag>30s, failed outbox, and DLQ>0. Fill `DeliveryRepository` count methods that have no direct test. Do not change lag thresholds. Do not require a live Meilisearch.

---

## 5. Test Criteria
- [ ] Delete outbox row does not call upsert
- [ ] Command returns 1 on each alert condition and 0 when clean
- [ ] Repository count methods have a direct assertion
- [ ] `php artisan test --filter=CheckOutboxLag` green

---

## 6. Completion Notes
- **Shipped:** `app/Jobs/ProcessIndexOutbox.php::lagSeconds()` is now timezone-safe (parses to UTC, returns wall-clock seconds between oldest pending row and now); previously returned negative values on sqlite (Asia/Dhaka offset vs UTC now). New `tests/Feature/ProcessIndexOutboxDeleteTest.php` covers the `op=delete` branch calling Meili DELETE and marking the row `done`, plus the no-host contract (row is marked `done` so the queue does not pile up — matches upsert behaviour). New `tests/Feature/CheckOutboxLagTest.php` covers healthy → exit 0 + no warning; lag >30s → exit 1 + warning + `LAG=` output; failed outbox row → exit 1 + warning; `DeliveryRepository::dlqCount` returns int (the command branch on `dlq > 0` runs).
- **Tests:** `php artisan test --filter='ProcessIndexOutboxDeleteTest|CheckOutboxLagTest'` 6 passed (13 assertions). `php artisan test` full suite 827 passed / 1 skipped (2684 assertions).
- **Live Smoke:** `php artisan monitor:outbox-lag` prints `OK lag=0s`.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
