# Task: M13-API-007 — Bulk ZIP Media Export (FR-MED-009, NFR §7.1)

**Status:** ✅ Completed
**Dependencies:** M13-API-001, M13-API-006
**Parent ADR:** DEC-007, FR-MED-009, NFR §7.1

---

## 1. Contract (What)
- **Endpoint:** `POST /api/v1/media/export`
- **Auth:** `resolve.client:required` (API key with `media:read` scope or active portal user session)
- **Payload:**
  ```json
  {
    "asset_ids": ["01M2...", "01M3..."],
    "variant": "original",
    "async": false
  }
  ```
- **Validation:**
  - `asset_ids`: array, required, min 1, max 50 items.
  - `variant`: nullable, string in `original,large,medium,small,thumb`.
  - `async`: nullable, boolean.
- **Output:**
  - Synchronous (`async = false`): `200 OK`
    ```json
    {
      "download_url": "https://...",
      "expires_in": 300,
      "filename": "media-export-01M2...zip",
      "asset_count": 2,
      "size_bytes": 102400
    }
    ```
  - Asynchronous (`async = true`): `202 Accepted`
    ```json
    {
      "message": "Export job queued",
      "asset_count": 2
    }
    ```
- **Guards:**
  - Media kind entitlement verification (`media_kinds` in client package).
  - Monthly media quota check (`media_used + count <= media_quota`).
  - Individual asset ledgering in `downloads` table (`item_type = 'media'`).

---

## 2. Logic (How)
1. Create `App\Services\Media\MediaZipExportService`:
   - Validates asset kind entitlements via `EntitlementResolver`.
   - Checks quota availability for the total number of requested assets via `QuotaService`.
   - Uses `ZipArchive` to stream files into a temporary archive on disk.
   - Uploads resulting ZIP to storage under `exports/media-export-{ulid}.zip`.
   - Generates 5-minute presigned download URL.
   - Bulk inserts records into `downloads` table and increments `download_count` on all included assets.
2. Create `App\Jobs\ExportMediaZipJob` for queued execution when `"async": true`.
3. Add `export` method to `MediaController` and expose route on `POST /api/v1/media/export`.

---

## 3. Context (Where)
- **Files Created:**
  - `app/Services/Media/MediaZipExportService.php`
  - `app/Jobs/ExportMediaZipJob.php`
  - `tests/Feature/Api/MediaZipExportTest.php`
- **Files Modified:**
  - `app/Http/Controllers/Api/MediaController.php`
  - `routes/api.php`

---

## 4. Test Criteria
- [x] Unauthenticated request returns 401.
- [x] Missing or >50 assets returns 422.
- [x] Synchronous export produces ZIP, returns download URL, and ledgers downloads.
- [x] Asynchronous export queues `ExportMediaZipJob` and returns 202.
- [x] Media kind mismatch (e.g. video for photo-only package) returns 403.
- [x] Quota insufficient for requested asset count returns 429.
- [x] Portal user exports ZIP with `client_user_id` correctly stored in audit ledger.

---

## 5. Completion Notes
- **Shipped:** Built `MediaZipExportService` with `ZipArchive`, `ExportMediaZipJob`, added `POST /api/v1/media/export` endpoint with validation, quota, and entitlement checks.
- **Tests:** `php artisan test --filter=MediaZipExportTest` (7 passed, 26 assertions).
