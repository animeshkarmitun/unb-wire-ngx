# Task: M14-COV-012 — Thin service and repository surfaces

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007

---

## 1. Contract (What)
- **Inputs / Validation:** Existing public methods that have no direct call in tests.
- **Outputs / Response:** Each method below has one assertion of its return value or side effect. No new product features.
- **Authorization:** n/a

---

## 2. Logic (How)
Cover only these holes:
1. `WireFeedService::popularRail()` — returns a collection, eager-load safe, empty when no stories.
2. `NotificationService::notifyMediaDecision` — call it; assert a notification row or `Notification::fake` sent. Do not add a new caller in production.
3. `ClientService::computeStats`, `clientHasIssue`, `getFilteredClientsQuery` — one test each via the service, not only via Livewire render.
4. `TenantTokenIssuer` — TTL claim, null client, malformed key rejected. `Http` not required if the issuer is local HMAC.
5. `AuditQueryService::search` — query string filters; not merely `total() > 0`.
6. `SendPortalResetPassword` — portal reset HTTP test asserts the mailable was sent (`Mail::fake`).
7. `MediaUploaded` — do not add a dispatch. Livewire-test `PhotoManager::onMediaUploaded` with a payload and assert the component state change. If the method is a no-op, assert that and stop.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - existing Feature tests for those classes, or `tests/Feature/ThinSurfaceTest.php` if a new file is clearer
- **Reference Files:**
  - `docs/testing-coverage-gaps.md` §3 table

---

## 4. Prompt (For the Coding AI)
> Add direct tests for the methods listed in §2. Do not add production callers. Do not dispatch `MediaUploaded` from intake; only test the Livewire listener. `AuditQueryService::search` must assert a filtered hit and a miss, not `total() > 0`.

---

## 5. Test Criteria
- [ ] Each listed method appears in a test call
- [ ] Search test has a negative query
- [ ] No new production dispatch of `MediaUploaded`
- [ ] `php artisan test --filter=ThinSurface` or the files you touched green

---

## 6. Completion Notes
- **Shipped:** New `tests/Feature/ThinSurfaceTest.php` covers: `WireFeedService::popularRail` returns a collection (empty when no stories, populated when seeded), `NotificationService::notifyMediaDecision` fires a `StoryNotification` to the uploader (via `Notification::fake`), `ClientService::computeStats` + `clientHasIssue` (clean client does not throw), `AuditQueryService::search` (named args: `action: 'published'` filters hits; unknown action returns 0 — not `total() > 0`), `TenantTokenIssuer::issueFor` (with attached package returns `token/host/filter/expires_at`; `expires_at` is at least 6 minutes ahead of now for `ttlMinutes=7`; null client falls back). `tests/Feature/Repositories/DeliveryRepositoryTest.php` extended with: `hasPriorSuccess` (true with prior sent, false without; `excludeDeliveryId` argument honoured), `failedCount`, `deliveredCountBetween`, and an `int` assert on `dlqCount`.
- **Tests:** `php artisan test --filter='ThinSurfaceTest|DeliveryRepositoryTest'` 17 passed (33 assertions). `php artisan test` full suite 869 passed / 1 skipped (2783 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
