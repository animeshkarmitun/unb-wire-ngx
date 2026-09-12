# Task: M13-API-004 — Story Download API + DownloadGateService (FR-PRT-004, NFR §15.1)

**Status:** ✅ Completed
**Dependencies:** M13-API-001
**Parent ADR:** DEC-007, FR-PRT-004, NFR §15.1

---

## 1. Contract (What)
- **Endpoints:**
  - `GET /api/v1/portal/story/{publicId}/download?format=json|nitf|newsml`
  - `GET /api/v1/story/{publicId}/download?format=json|nitf|newsml`
- **Auth:** `resolve.client:required` (supports both API key and Sanctum portal session)
- **Parameters:**
  - `format` (optional, default: `json`): `json` (UNB v1 JSON), `nitf` (IPTC NITF XML), `newsml` (IPTC NewsML-G2 XML)
- **Response:** File attachment with correct `Content-Type` and `Content-Disposition`.
- **Errors:**
  - 401: Unauthenticated
  - 403: Client not entitled to story's language or category
  - 404: Story not found / draft / archived
  - 422: Unsupported format parameter
- **Audit:** Records each download in `downloads` table (`item_type = 'story'`, `item_id`, `format`, `size_bytes`, `client_id`, `client_user_id`, `ip`, `created_at`).

---

## 2. Logic (How)
1. Create `App\Services\Download\DownloadGateService`:
   - `downloadStory(Story $story, Client $client, ?int $clientUserId, string $format): WireOutput`
   - Verifies language and category entitlements via `EntitlementResolver`.
   - Generates wire output using `WireFormatFactory`.
   - Records row in `downloads` table.
2. Update `WireFormatFactory` to accept format aliases (`json`, `nitf`, `newsml`, `xml`).
3. Add `download()` method to `PortalController`:
   - Validates story existence and publication state.
   - Enforces authentication and invokes `DownloadGateService`.
   - Returns file attachment response.
4. Add routes in `routes/api.php` under `resolve.client:required`.

---

## 3. Context (Where)
- **Files Created:**
  - `app/Services/Download/DownloadGateService.php`
  - `tests/Feature/Api/StoryDownloadTest.php`
- **Files Modified:**
  - `app/Services/Delivery/WireFormatFactory.php`
  - `app/Http/Controllers/Api/PortalController.php`
  - `routes/api.php`

---

## 4. Test Criteria
- [x] Unauthenticated request returns 401.
- [x] Entitled client downloads JSON format with valid content and download ledger record.
- [x] Entitled client downloads NITF format with XML content-type and `<nitf` tag.
- [x] Entitled client downloads NewsML format with XML content-type and `<newsItem` tag.
- [x] Unentitled client (language or category mismatch) receives 403.
- [x] Draft / unpublished story returns 404.
- [x] Portal user downloads story with `client_user_id` properly ledged.
- [x] Unsupported format returns 422.

---

## 5. Completion Notes
- **Shipped:** Implemented `DownloadGateService`, added `WireFormatFactory` alias resolution, added `PortalController::download`, exposed routes on `/portal/story/{id}/download` and `/story/{id}/download`.
- **Tests:** `php artisan test --filter=StoryDownloadTest` (8 passed, 21 assertions).
