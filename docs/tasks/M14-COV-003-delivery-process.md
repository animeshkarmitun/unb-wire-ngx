# Task: M14-COV-003 — delivery:process and webhook failure marking

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-005, DEC-007; domain.md §3 delivery guarantees (FR-DST-006)

---

## 1. Contract (What)
- **Inputs / Validation:** Queued/failed `deliveries` with webhook channel config. HTTP fake 200, 500, and throw.
- **Outputs / Response:** Non-2xx or thrown HTTP sets `deliveries.status=failed` and increments `attempt_count`. `delivery:process` retries after backoff, skips inactive channels and FTP, fails missing stories, auto-pauses the channel at the configured threshold.
- **Authorization:** n/a

---

## 2. Logic (How)
1. Read `app/Jobs/FanoutStory.php` `sendWebhook` and `app/Console/Commands/ProcessDeliveriesCommand.php`.
2. If non-2xx leaves the row `queued`, change it to `failed` + attempt bump + `recordChannelFailure`, matching the thrown-exception path. Idempotency key unchanged.
3. New `tests/Feature/ProcessDeliveriesCommandTest.php` calls `$this->artisan('delivery:process')`. Cases: success → `sent`/`delivered`; 500 → `failed` and attempt+1; backoff not elapsed → row unchanged; attempt_count >= max → not selected; inactive channel skipped; FTP skipped; missing story → failed with error; N failures → channel paused (use the existing threshold, do not invent a new one).
4. Do not mock `ProcessDeliveriesCommand`. `Http::fake` is allowed.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Jobs/FanoutStory.php` (only if non-2xx does not mark failed)
  - `tests/Feature/ProcessDeliveriesCommandTest.php`
  - extend `tests/Feature/FanoutAdvancedTest.php` for the non-2xx mark
- **Reference Files:**
  - `app/Console/Commands/ProcessDeliveriesCommand.php`
  - `docs/testing-coverage-gaps.md` §4 webhook row

---

## 4. Prompt (For the Coding AI)
> Close the untested `delivery:process` command and the webhook non-2xx hole. A non-2xx response must mark the delivery `failed` and increment `attempt_count` in both `FanoutStory` and the command. Add Feature tests that run `delivery:process` for success, 500, backoff skip, max retries, inactive channel, FTP skip, missing story, and auto-pause. Do not weaken asserts to "queued". Do not add a new scheduler.

---

## 5. Test Criteria
- [ ] `php artisan test --filter=ProcessDeliveriesCommandTest` green
- [ ] Fan-out non-2xx test asserts `failed`, not `queued`
- [ ] `php -l` clean on touched PHP

---

## 6. Completion Notes
- **Shipped:** `app/Jobs/FanoutStory.php::sendWebhook` now marks the delivery `failed` and bumps `attempt_count` on non-2xx or thrown HTTP (mirroring `ProcessDeliveriesCommand::handleFailure`). Added `markDeliveryFailed` helper. New `tests/Feature/ProcessDeliveriesCommandTest.php` covers: success → `sent` + 200, non-2xx → `failed` + attempt+1 + response_code + error, thrown exception → `failed` + error, backoff window skip, max-retries skip, inactive channel skip, FTP skip, missing story → `failed` + `Story not found`, auto-pause channel at threshold, killed story sends webhook.
- **Tests:** `php artisan test --filter=ProcessDeliveriesCommandTest` 10 passed (31 assertions). `php artisan test` full suite 813 passed / 1 skipped (2659 assertions).
- **Live Smoke:** `php artisan delivery:process --limit=1` exits 0 on empty queue.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
