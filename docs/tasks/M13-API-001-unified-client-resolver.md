# Task: M13-API-001 — Unified Client Resolver Middleware (Auth Mismatch Fix)

**Status:** ✅ Completed
**Dependencies:** M12-PROFILE-003
**Parent ADR:** DEC-007, FR-DST-002, FR-PRT-004, FR-PRT-005

---

## 1. Contract (What)
- **Inputs:**
  - `Authorization: Bearer <token>` (either Sanctum `ClientUser` token or `ClientApiKey` raw key)
  - `X-API-Key: <key>` header (for programmatic clients)
- **Outputs:**
  - Sets request attributes:
    - `client` (`Client|null`)
    - `clientUser` (`ClientUser` if Sanctum token)
    - `clientApiKey` (`ClientApiKey` if API key)
  - Mode `:required`: 401 if unauthenticated, 403 if client is suspended or user is deactivated.
  - Mode `:optional`: proceeds with `client = null` for guest visitors; 403 if client suspended or user deactivated.
- **Scope & Endpoints:**
  - `POST /api/v1/portal/search-token` (`resolve.client:optional`)
  - `GET /api/v1/portal/context` (`resolve.client:optional`)
  - `GET /api/v1/media/{id}/download` (`resolve.client:required`)

---

## 2. Logic (How)
1. Check Sanctum bearer token via `PersonalAccessToken::findToken($bearer)`. If found for `ClientUser`:
   - Enforce `$user->status === 'active'`, else 403.
   - Enforce `$client->status === 'active'`, else 403.
   - Set request user resolver, `clientUser`, and `client`.
2. Check API key via `$apiKeySvc->authenticate($rawKey)`. If found:
   - Enforce client `status === 'active'`, else 403.
   - Enforce per-key rate limiting (RPM from `client_api_keys.rate_limit_rpm`).
   - Set `clientApiKey` and `client`.
3. If neither authenticated:
   - If `:optional`, set `client = null` and proceed.
   - If `:required`, return 401 JSON.
4. Refactor `PortalController::searchToken` and `PortalController::context` to read the resolved `client` attribute rather than doing redundant manual API-key-only lookups.
5. Refactor `MediaController::clientPresigned` to accept both Sanctum client users and API keys with proper download ledgering of `client_user_id`.

---

## 3. Context (Where)
- **Files Created:**
  - `app/Http/Middleware/ResolveClient.php`
- **Files Modified:**
  - `bootstrap/app.php` (registered `resolve.client` alias)
  - `routes/api.php`
  - `app/Http/Controllers/Api/PortalController.php`
  - `app/Http/Controllers/Api/MediaController.php`
  - `tests/Feature/PortalApiTest.php`
  - `tests/Feature/MediaTest.php`

---

## 4. Test Criteria
- [x] Portal user (Sanctum) calls `/portal/context` → returns real client data (not null).
- [x] Portal user calls `/portal/search-token` → returns entitled tenant token.
- [x] Portal user calls `/media/{id}/download` → returns presigned URL and records download in `downloads` ledger with `client_user_id`.
- [x] Programmatic API key client still works for all three endpoints.
- [x] Suspended client receives 403.
- [x] Deactivated portal user receives 403.
- [x] Guest visitor on `/portal/search-token` receives public guest token.
- [x] Guest visitor on `/portal/context` receives `{ client: null, saved_searches: [] }`.
- [x] Unauthenticated request on `/media/{id}/download` receives 401.

---

## 5. Completion Notes
- **Shipped:** Implemented `ResolveClient` middleware, registered alias in `bootstrap/app.php`, refactored `PortalController` and `MediaController`, updated route definitions with `:optional` and `:required` modes.
- **Tests:** `php artisan test` (521 passed, 1 skipped, 1845 assertions). All portal, auth, media, and middleware tests pass clean.
- **Schema Parity:** `php scripts/schema-parity-check.php` all checks PASSED.
