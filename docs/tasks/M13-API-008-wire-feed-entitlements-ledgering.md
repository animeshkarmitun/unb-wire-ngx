# Task: M13-API-008 — Wire Feed Delivery, Entitlement Enforcement & Download Ledgering E2E Suite

**Status:** ✅ Complete
**Dependencies:** M13-API-001 (Unified Client Resolver), M13-API-002 (Feed Entitlement Programmatic), M13-API-004 (Story Download API), M13-API-005 (Kill Tombstones), M13-API-006 (Quota Gate), M13-API-007 (Bulk Export)
**Contracts & ADRs:** DEC-007 (Packages & Entitlements), FR-DST-002 (Single Entitlement Source), FR-DST-004 (Tombstone Retractions), FR-API-001..010

---

## 1. The Contract (What)

### 1.1 Client API Key Authentication (`EnsureClientApiKey.php` & `ResolveClient.php`)
- Supported via `Authorization: Bearer <raw_key>` or `X-API-Key: <raw_key>`.
- Raw keys are looked up by `sha256(raw_key)` in `client_api_keys.key_hash`.
- Unauthenticated requests return 401 (`Missing API key` or `Unauthenticated`).
- Invalid / revoked keys return 401 (`Invalid API key`).
- Scope enforcement:
  - `feed:read` required for `/api/v1/feed`.
  - `media:read` required for `/api/v1/media/{id}/download` and `/api/v1/media/export`.
  - Requests missing required scope return 403 (`Insufficient scope`).
- Rate limiting:
  - Each key specifies `rate_limit_rpm` (default 60).
  - Exceeding limit returns 429 (`Rate limit exceeded`) with `Retry-After` header.
- Status gates:
  - Suspended client returns 403 (`Client suspended`).

### 1.2 Declarative Entitlement Filtering (`FR-DST-002`)
- Endpoint: `GET /api/v1/feed`
- Driven by `EntitlementResolver::forClient($client)`.
- Client with English package receives only stories where `language = 'en'`.
- Client with category restrictions receives only stories matching their permitted category IDs.
- Clients with different entitlements receive cache-isolated responses (`feed:v1:{client_id}:{hash}`).

### 1.3 Kill & Retraction Tombstones (`DEC-007`, `FR-DST-004`)
- When a previously published story is killed (`status = 'killed'`, `published_at` is not null):
  - The feed outputs a tombstone payload:
    - `public_id`: Story's ULID.
    - `headline`: Original headline.
    - `brief`: `'STORY KILLED / RETRACTED'`.
    - `status`: `'killed'`.
    - `is_killed`: `true`.
    - `killed_at`: ISO8601 timestamp.
  - This allows client systems to automatically retract the article from their CMS and portals.

### 1.4 Story Download API & Ledgering (`GET /api/v1/story/{publicId}/download`)
- Formats supported: `json-unb-v1` (default), `newsml-g2`, `nitf`.
- Checks client entitlement (language, category) and quota.
- Returns 200 with format-specific `Content-Type` and attachment `Content-Disposition`.
- Ledgers download in `downloads` table:
  - `client_id`, `item_type: 'story'`, `item_id`, `format`, `size_bytes`, `ip`.

### 1.5 Media Download API & Ledgering (`GET /api/v1/media/{id}/download`)
- Validates `media:read` scope and client media quota.
- Returns 200 JSON with short-lived presigned URL (`url`, `expires_in: 300`).
- Ledgers download in `downloads` table:
  - `client_id`, `item_type: 'media'`, `item_id`, `size_bytes`.

### 1.6 Bulk Media Export (`POST /api/v1/media/export`)
- Validates `media:read` scope and `asset_ids` array (min: 1, max: 50).
- Supports synchronous or queued export (`async: true`).

---

## 2. Verification Criteria
- [x] Automated Playwright tests cover all contracts in `tests/e2e/api-media-billing.spec.ts` (19 passed).
- [x] All quality gates pass (`schema-parity-check.php`, `php artisan test`, `npm run build`).

### Completion Notes
- Enhanced `tests/e2e/helpers/seed-data.php` with `wire_api` fixture and `download_count` action.
- Added 10 exhaustive E2E test cases to `tests/e2e/api-media-billing.spec.ts` bringing the suite to 19 passing tests.
- Formatted `public_id` trimming defensively in `JsonUnbV1Formatter`, `NewsmlG2Formatter`, and `NitfFormatter`.
- Added non-production local storage fallback to `PresignedUrlService` for `public` and `local` disks.
- Verified PHPUnit (587 passed, 1 skipped) and Schema Parity (all 12 checks passed).
