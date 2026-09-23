# Security Audit Report — UNB Wire

**Task:** M13-SEC-001 (COS-27) · **Date:** 2026-09-23 · **Scope:** `app-data` security expectations (NFR §8) + the 12 audit areas in COS-27. Code + config + dependency sweep; critical findings fixed in this change set (PR notes at bottom).

**Verdict scale:** PASS · WARN (acceptable/deferred, noted) · FAIL (fixed here or tracked).

---

## Checklist

| # | Area | Verdict | Evidence & findings |
|---|------|---------|---------------------|
| 1 | Authentication — sessions, passwords, tokens | **PASS** | Session driver `database` (revocable rows); staff login throttled via `RateLimiter` + `RateLimitHelper::attempts('staff_login')` (`LoginRequest`); Bcrypt via Laravel `Hash`; `client_api_keys` sha256-hashed with `expires_at`/`revoked_at` + 1h rotation overlap (`ApiKeyService`, tested); Sanctum reserved for field devices (deferred, DEC-008). *Note:* session cookie flags are framework defaults (`http_only`, `same_site=lax`). |
| 2 | Authorization — RBAC bypass, IDOR | **PASS** | All 13 admin routes carry `rbac:module,action` (`routes/web.php`) + superadmin bypass tested (`RbacEndpointTest`, `SuperadminTest`); Livewire write actions `assertCan` server-side; TUS sessions ownership-checked (`TusUploadTest::user cannot access another users upload session`); downloads pass `DownloadGateService` (entitlement→quota); portal feed/tombstones entitlement-filtered (`FeedTombstoneTest`). No IDOR found on story/client/media public-id endpoints. |
| 3 | SQL injection | **PASS** (1 fix) | All raw SQL audited: `whereRaw('LOWER(name) LIKE ?', [$s])` bound; `selectRaw`/`orderByRaw`/`DB::raw` elsewhere static. **Fixed:** `AiService` concatenated provider token counts into `DB::raw('… + '.$totalTokens)` — now `((int) …)` casts (defense-in-depth; values originate from the AI provider response). |
| 4 | XSS — sanitizer, Blade escaping | **PASS** | `HtmlSanitizer::clean` allowlist (tags+attrs), strips `on*` handlers, blocks `javascript:`/`vbscript:`/`data:text/html` hrefs and scheme-restricts `src`; applied at all `body_html` entry points (`createDraft`/`updateDraft`/`syncFromDoc`/notes). Raw Blade output `{!! !!}` only in 4 places — all through `HtmlSanitizer::clean`, `safe_body_html` accessor (sanitizes), or `strip_tags`. Wizard JS interpolations go through `esc()` (`wizard-main.js:9`). *Warn:* live-preview `innerHTML` mirrors the editor's own content inside the documented `wire:ignore` wrapper (self-only, server-side sanitize on save). |
| 5 | CSRF | **PASS** | Laravel 11 web-group `VerifyCsrfToken` active (not disabled, no `except` list in `bootstrap/app.php`); Livewire posts carry the token; state-changing APIs authenticate by API key (no cookie session → no CSRF surface). |
| 6 | File uploads | **PASS** | `PhotoManager::handleUploads` validates `image\|mimes:jpg,jpeg,png,webp\|max:10240` (MIME + 10MB); storage via `store()` (hashed generated name → no path traversal of user filenames); TUS create requires auth + role (`TusCreateRequest`, `TusUploadTest`). *Warn (tracked):* the TUS resumable path currently discards chunk bytes (functional gap FR-MED-006/009, not a vuln). |
| 7 | Secrets — git hygiene, key hashing | **PASS** (1 warn) | `git ls-files`: no `.env`, no pem/key files (only `.env.example`, `.env.testing*`); API keys stored as sha256 `key_hash`, raw never persisted. **Fixed (part of #11):** removed hardcoded demo raw-key constants and hash-prefix-derived "raw" display from `DeliverySettings` source. *Warn:* masked-key cosmetic demo strings remain in the UI fallbacks (visibly masked only). |
| 8 | Security headers | **FAIL → FIXED** | No headers middleware existed (grep of middleware/routes: empty). **Fix:** `App\Http\Middleware\SecurityHeaders` appended to the web group — `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, `Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; object-src 'none'`, and `Strict-Transport-Security` **only over HTTPS**. Tested (`SecurityHeadersTest`). *Warn:* full script-src CSP deferred — the admin surface is inline-script heavy (Livewire/Alpine/Quill bootstrap); a nonce-based CSP is a dedicated hardening task. |
| 9 | Rate limiting | **PASS** | Named limiters (`config/rate-limiting.php`, M13-RATE): `staff_login`, `portal_login` 10/min, `portal_forgot_pw` 3/min, `portal_reset_pw` 5/min, `portal_feed` 60/min, `portal_story` 120/min, `portal_search_token` 60/min, `portal_session` 60/min, `client_feed` 60/min, `media_download`; per-key `rate_limit_rpm` on API auth; tested (`RateLimitConfigTest`, `ClientApiKeyTest::test_rate_limit`, `PortalApiTest` rate suites). |
| 10 | Dependency audit | **PASS** | `composer audit`: **0** advisories · `npm audit`: **0** vulnerabilities (2026-09-23). |
| 11 | API key security | **FAIL → FIXED** | sha256 hash storage ✓, rotation with 1h overlap ✓ (both tested). Violations found: (a) `toggleRevealKey` re-revealed the raw key indefinitely; (b) `rawApiKey` was derived from `key_hash` prefix (hash disclosure) and fell back to hardcoded demo "keys". **Fix:** raw key is shown **once** — auto-revealed at generation, hide **consumes** it (`rawApiKey` cleared, no re-reveal), never derived from `key_hash`, demo constants removed. **Also fixed:** `ApiKeyService::issue/rotate/revoke` now write `audit_logs` (`api_key.issued/rotated/revoked`) — closing the FR-NTF-003 gap for key ops. Tested (`DeliverySettingsTest::test_api_key_reveal_is_one_time_only`, `test_api_key_lifecycle_is_audited`). |
| 12 | Portal API tenant isolation | **PASS** | `ResolveClient` binds one client per credential; entitlement filter applied on feed/story/tombstone/search-token; per-client cache isolation tested (`ClientFeedEntitlementTest::cache is isolated between clients with different entitlements`); downloads/quota scoped per client (`QuotaService`, `DownloadGateService`); portal users scoped to their client (`PortalAccountService`, `PortalApiTest`). |

---

## Summary

| Verdict | Count | Items |
|---------|-------|-------|
| PASS | 9 | 1, 2, 4, 5, 6, 9, 10, 12 (+ 3 after fixes) |
| FAIL → FIXED | 3 | 8 (headers), 11 (key show-once + hash leak + demo constants), 3 (raw SQL cast hardening) |
| WARN (tracked/deferred) | — | full CSP nonce refactor; TUS ingest completeness (FR-MED-006/009); masked cosmetic demo strings; Sanctum field-device auth (DEC-008) |

## Critical findings fixed in this change set

1. **Security headers missing** → `SecurityHeaders` middleware (web group).
2. **API raw key re-revealable + derived from hash** → show-once semantics; no hash-derived display; demo constants removed.
3. **API key lifecycle unadited** → `api_key.issued/rotated/revoked` audit records.
4. **Value-interpolated `DB::raw`** in `AiService` → int casts.

No exploitable injection/IDOR/XSS/CSRF path was found unfixed.
