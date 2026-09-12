# Task: M11-HOOK-004 — Delivery queue processor (retry worker)

**Status:** ⏳ Pending
**Dependencies:** M11-HOOK-001
**Parent ADR:** FR-DST-002, DEC-005 (queue-backed distribution)

---

## 1. Contract (What)
- **Inputs:** `deliveries` table rows where `status = 'queued'` or (`status = 'failed'` AND `attempt_count < max_retries`)
- **Outputs:** For each delivery: attempt dispatch → update status to `sent`/`failed`, increment `attempt_count`, set `response_code`/`error`
- **Scheduling:** Laravel console command registered in `routes/console.php`, runs every minute via scheduler
- **Backoff:** Exponential: attempt 1 = 60s, attempt 2 = 120s, attempt 3 = 240s (configurable via `settings.delivery.backoff_seconds`)

---

## 2. Logic (How)
1. Create `App\Jobs\ProcessQueuedDeliveries` (or artisan command `delivery:process`).
2. Query `deliveries` where `status IN ('queued', 'failed')` AND `attempt_count < settings.delivery.max_retries` (default 5).
3. For each delivery:
   - Load channel (`client_channels`) and decode config.
   - If `channel.type === 'webhook'`: dispatch webhook (reuse `WebhookPayloadBuilder` + `WebhookSigner`).
   - If `channel.type === 'ftp'`: skip for now (handled by M11-FTP-003).
   - On success: set `status = 'sent'`, `sent_at = now()`, `response_code`, call `recordChannelSuccess()`.
   - On failure: increment `attempt_count`, set `error` message. If max retries exceeded, set `status = 'failed'` permanently.
4. Register in `routes/console.php`: `Schedule::command('delivery:process')->everyMinute()`.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Console/Commands/ProcessDeliveriesCommand.php`
- **Files to Modify:**
  - `routes/console.php` (register scheduled command)
- **Reference:**
  - `app/Jobs/FanoutStory.php` (current inline webhook dispatch)
  - `app/Repositories/ClientRepository.php` (recordChannelSuccess/Failure)
  - `app/Models/Delivery.php`, `database/migrations/*deliveries*`
  - `app/Livewire/Admin/DeliverySettings.php` Card 5 engine rules (retry/backoff/auto-pause)
- **Tests:**
  - `tests/Feature/ProcessDeliveriesTest.php` — retry logic, backoff, max attempts, status transitions

---

## 4. Prompt (For the Coding AI)
> Implement M11-HOOK-004. Create `ProcessDeliveriesCommand` artisan command. Query deliveries in queued/failed status, attempt dispatch (webhook only for now), update status/attempt_count/response_code. Register in scheduler. Write tests for: successful retry, max retries exceeded → permanent fail, backoff timing, channel auto-pause integration.

---

## 5. Test Criteria
- [ ] Queued webhook deliveries are retried and marked `sent` on success
- [ ] Failed deliveries increment `attempt_count`
- [ ] Max retries exceeded → `status = 'failed'` permanently
- [ ] Channel auto-pause triggers after threshold failures
- [ ] Scheduler registration works (`php artisan schedule:list` shows command)
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
