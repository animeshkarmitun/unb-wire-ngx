# Task: M11-HOOK-002 — HMAC-SHA256 webhook signature

**Status:** ⏳ Pending
**Dependencies:** M11-HOOK-001
**Parent ADR:** FR-DST-002, NFR §8 Security

---

## 1. Contract (What)
- **Inputs:** Webhook payload (JSON), per-channel signing secret from `client_channels.config.signing_secret`
- **Outputs:** HTTP headers added to webhook POST:
  - `X-UNB-Signature: sha256=<hex_hmac>`
  - `X-UNB-Timestamp: <unix_epoch>`
  - `X-UNB-Event: story.published`
  - `Content-Type: application/json`
- **Signing formula:** `HMAC-SHA256(timestamp + "." + json_payload, signing_secret)`
- **Authorization:** N/A (internal job)

---

## 2. Logic (How)
1. Add `signing_secret` to webhook channel config during onboarding (`ClientService::onboardClient`) — generate via `Str::random(40)`.
2. Create `App\Services\Delivery\WebhookSigner` service with `sign(string $payload, string $secret, int $timestamp): string`.
3. In `FanoutStory.php`, before `Http::post()`:
   - Serialize payload to JSON.
   - Generate timestamp, compute HMAC.
   - Add headers to the HTTP request.
4. In `DeliverySettings.php` — display signing secret (masked, reveal toggle) alongside API key.
5. On channel creation/rotation, allow regenerating signing secret.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Services/Delivery/WebhookSigner.php`
- **Files to Modify:**
  - `app/Jobs/FanoutStory.php` (add signing headers to Http::post)
  - `app/Services/ClientService.php` (`onboardClient`, `toggleChannel` — add `signing_secret`)
  - `app/Livewire/Admin/DeliverySettings.php` (display signing secret)
- **Tests to Create/Modify:**
  - `tests/Feature/WebhookSignerTest.php` — verify HMAC correctness, timestamp inclusion
  - Update `FanoutAdvancedTest` — assert headers present

---

## 4. Prompt (For the Coding AI)
> Implement M11-HOOK-002. Create `WebhookSigner` service. Update `FanoutStory` to sign webhook requests with HMAC-SHA256. Add `signing_secret` generation to `ClientService::onboardClient()`. Display masked secret in `DeliverySettings`. Write tests verifying HMAC correctness and header presence.

---

## 5. Test Criteria
- [ ] `WebhookSigner::sign()` produces correct HMAC-SHA256 hex digest
- [ ] Webhook POST includes `X-UNB-Signature`, `X-UNB-Timestamp`, `X-UNB-Event` headers
- [ ] New channels get auto-generated `signing_secret`
- [ ] Existing tests pass with 0 regressions
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
