# Task: M14-COV-002 — Run scheduler closures for real

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; domain.md §2.5–2.6; `routes/console.php`

---

## 1. Contract (What)
- **Inputs / Validation:** Stories with `embargo_until` in the past (`approved`) and future; published stories older/newer than 12 months; expired `upload_sessions`.
- **Outputs / Response:** Running the named schedule callback (not a hand-written `update`) publishes, archives, or deletes as the closure does today, and writes the side effects the closure already contains.
- **Authorization:** n/a (worker)

---

## 2. Logic (How)
1. Replace `SchedulerAndMiddlewareTest::test_embargo_lift_*` and `test_archive_sweep_*`. They must invoke the closures registered in `routes/console.php` (extract each closure to an invokable class if that is the only way to call it without `Artisan::call('schedule:run')` firing every task). Do not reimplement the query in the test and then update the row.
2. Embargo lift assert: past `embargo_until` + `approved` → `published`, `published_at` set, `index_outbox` upsert row, `FanoutStory` dispatched. Future embargo stays `approved`. No `story_events` required unless the closure writes them — do not add events in this task.
3. Archive sweep assert: `published_at` older than 12 months → `archived`, outbox `main` delete + `archive` upsert. Recent published row untouched.
4. Upload janitor: expired `upload_sessions` row deleted; unexpired row remains.
5. `Cache::forget('portal:feed:*')` is not a wildcard. If the lift is supposed to drop portal feed cache, replace it with a real invalidation (tag or known keys) and assert a primed key is gone. If no feed cache key exists, delete the no-op forget and assert fan-out + outbox only. Do not leave a fake forget.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `routes/console.php` (extract closures if needed)
  - `tests/Feature/SchedulerAndMiddlewareTest.php`
  - new `app/Console/Scheduling/` invokables only if extraction is required
- **Reference Files:**
  - `docs/testing-coverage-gaps.md` §4 embargo/archive rows
  - `docs/knowledge-inventory/domain.md` §2.5

---

## 4. Prompt (For the Coding AI)
> The embargo and archive tests in `tests/Feature/SchedulerAndMiddlewareTest.php` update rows themselves. Call the real `embargo-lift`, `archive-sweep`, and `upload-janitor` callbacks. Assert outbox rows and `FanoutStory` dispatch for lift; assert archive outbox delete+upsert; assert janitor deletes only expired sessions. Fix `Cache::forget('portal:feed:*')` so it either invalidates a real key or is removed. Do not add `story_events` unless you are only asserting what the closure already writes. phpunit only — no Playwright.

---

## 5. Test Criteria
- [ ] Lift test fails if the closure body is commented out
- [ ] Future embargo is not published
- [ ] Archive sweep does not touch a story published today
- [ ] Janitor leaves a live upload session
- [ ] `php artisan test --filter=SchedulerAndMiddlewareTest` green

---

## 6. Completion Notes
- **Shipped:** Three new invokables in `app/Console/Scheduling/` (`LiftEmbargoedStories`, `SweepArchivedStories`, `PruneUploadSessions`). `routes/console.php` now wires `Schedule::call(fn () => app(...)->handle())` for each. `LiftEmbargoedStories::invalidatePortalFeedCache()` enumerates active clients and forgets the actual `feed:v1:{clientId}:{md5(url)}` keys (`localhost:8000`, `127.0.0.1:8000`, `url()`); the broken `Cache::forget('portal:feed:*')` and `Cache::forget('feed:v1:*')` in `StoryService::transition` were replaced with that helper. New tests in `SchedulerAndMiddlewareTest` invoke the real invokables: lift publishes past embargoes, dispatches `FanoutStory` + `ProcessIndexOutbox`, writes an outbox upsert, and forgets a primed cache key; sweep archives only old published stories and writes both `main` delete and `archive` upsert outbox rows; janitor deletes only expired upload sessions.
- **Tests:** `php artisan test --filter=SchedulerAndMiddlewareTest` 11 passed (35 assertions). `php artisan test` full suite 803 passed / 1 skipped (2628 assertions). `php scripts/schema-parity-check.php` PASS.
- **Live Smoke:** `php artisan schedule:list` shows `embargo-lift`, `archive-sweep`, `upload-janitor`.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
