# Task: M14-COV-007 — API key overlap expiry

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; domain.md §3 API keys (FR-CLT-003)

---

## 1. Contract (What)
- **Inputs / Validation:** `ApiKeyService::rotate`. Clock at t=0 and t=1h+1s.
- **Outputs / Response:** Immediately after rotate, old and new raw keys authenticate. After `expires_at`, old key returns null and new key still authenticates. Revoked and suspended-client cases stay as they are.
- **Authorization:** n/a (service). HTTP test may hit the feed with both keys.

---

## 2. Logic (How)
1. Extend `tests/Feature/ApiKeyServiceExtendedTest.php`. Use `$this->travel()`.
2. Do not change the one-hour overlap unless the test proves the code uses a different window — then assert the code's window and note it. Prefer asserting `expires_at` from the row, then travel past that timestamp.
3. One HTTP assertion: feed 200 with new key, 401 with old key after expiry.
4. Assert rotate writes the audit row if that assert is missing.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Feature/ApiKeyServiceExtendedTest.php`
  - `tests/Feature/ClientApiKeyTest.php` only if the HTTP case fits there better
- **Reference Files:**
  - `app/Services/ApiKeyService.php` `rotate` / `authenticate`

---

## 4. Prompt (For the Coding AI)
> Add a time-travel test: after `ApiKeyService::rotate`, both keys work; after the old key's `expires_at`, only the new key works (service null + feed 401/200). Assert the rotate audit row. Do not change the overlap duration.

---

## 5. Test Criteria
- [ ] t=0 both keys authenticate
- [ ] past `expires_at` old key rejected, new key accepted
- [ ] `php artisan test --filter=ApiKey` green

---

## 6. Completion Notes
- **Shipped:** `tests/Feature/ApiKeyServiceExtendedTest.php` extended with: `rotate_old_key_dies_after_overlap_window` (both keys authenticate immediately after rotate, then `Carbon::setTestNow()` past `expires_at` rejects the old key while the new key still works), `rotate_old_key_returns_401_on_feed_after_overlap` (HTTP-level `401` on `/api/v1/feed` for the old key past overlap), and `rotate_writes_audit_row` (asserts `audit_logs` row with `action=api_key.rotated`).
- **Tests:** `php artisan test --filter=ApiKeyServiceExtendedTest` 11 passed (27 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
