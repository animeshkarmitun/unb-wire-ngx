# Task: M13-API-006 — Quota Enforcement Gate (FR-CLT-004, NFR §15.1)

**Status:** ✅ Completed
**Dependencies:** M13-API-001, M13-API-004
**Parent ADR:** DEC-007, FR-CLT-004, NFR §15.1

---

## 1. Contract (What)
- **Scope:**
  - Story downloads (`GET /api/v1/portal/story/{publicId}/download`)
  - Media downloads (`GET /api/v1/media/{id}/download`)
  - Client context (`GET /api/v1/portal/context`)
- **Quota Model:**
  - Defined in `clients.notes['tier_quotas']`:
    - `stories_quota` (int|null): monthly story limit (null = unlimited)
    - `media_quota` (int|null): monthly media limit (null = unlimited)
  - Monthly usage calculated dynamically for current calendar month:
    - Stories: count of `deliveries` (type = 'story', status = 'sent') + count of `downloads` (item_type = 'story')
    - Media: count of `downloads` (item_type = 'media')
- **Enforcement:**
  - If usage >= quota, return 429 Too Many Requests with descriptive message.
  - If quota is null/undefined, unrestricted downloads permitted.

---

## 2. Logic (How)
1. Create `App\Services\Billing\QuotaService`:
   - `getUsage(Client $client): array` → `['stories_quota', 'stories_used', 'media_quota', 'media_used']`
   - `canDownloadMedia(Client $client): bool`
   - `canDownloadStory(Client $client): bool`
   - `assertCanDownloadMedia(Client $client): void` (throws 429 HttpException)
   - `assertCanDownloadStory(Client $client): void` (throws 429 HttpException)
2. Inject into `MediaController@clientPresigned` and enforce media quota before presigned URL generation and ledgering.
3. Inject into `DownloadGateService@downloadStory` and enforce story quota before formatting and ledgering.
4. Refactor `PortalController@context` to compute client quota and usage using `QuotaService@getUsage`.

---

## 3. Context (Where)
- **Files Created:**
  - `app/Services/Billing/QuotaService.php`
  - `tests/Feature/Api/QuotaEnforcementTest.php`
- **Files Modified:**
  - `app/Services/Download/DownloadGateService.php`
  - `app/Http/Controllers/Api/MediaController.php`
  - `app/Http/Controllers/Api/PortalController.php`

---

## 4. Test Criteria
- [x] Client exceeding media quota receives 429 on media download.
- [x] Client exceeding story quota receives 429 on story download.
- [x] Client with unlimited quota (null) can download without restriction.
- [x] Portal context displays accurate stories and media quotas and usage counts.

---

## 5. Completion Notes
- **Shipped:** Implemented `QuotaService`, plugged quota assertions into media presigned URL controller, story download gate service, and portal context.
- **Tests:** `php artisan test --filter=QuotaEnforcementTest` (3 passed, 8 assertions) and `PortalApiTest` (28 passed, 356 assertions).
