# Task: M11-PORTAL-001 — Dynamic /context endpoint (replace hardcoded mock)

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** FR-DST-002

---

## 1. Contract (What)
- **Inputs:** Optional `Authorization: Bearer <api_key>` or `X-API-Key` header
- **Outputs:** JSON with real client data:
  ```json
  {
    "client": {
      "name": "The Daily Star",
      "initials": "DS",
      "tier": "Premium",
      "renews_at": "2027-01-15",
      "stories_quota": 2000,
      "stories_used": 342,
      "media_quota": 150,
      "media_used": 87
    },
    "saved_searches": [...]
  }
  ```
- **Data sources:**
  - `tier` → from active `client_packages.package.name`
  - `renews_at` → from `client_packages.end_date`
  - `stories_quota` / `media_quota` → from `clients.notes` JSON (`tier_quotas`)
  - `stories_used` → count of `deliveries` where `client_id` and `deliverable_type = 'story'` and `status = 'sent'` in current billing period
  - `media_used` → count of `downloads` where `client_id` in current billing period
  - `saved_searches` → from `clients.notes` JSON or future `saved_searches` table
- **Authorization:** Optional API key (guest gets generic public info)

---

## 2. Logic (How)
1. Update `PortalController::context()` (lines 65–87):
   - If API key present: resolve client, compute real stats.
   - If no key: return generic public info with null quotas.
2. Extract billing period: current month (1st to now).
3. Compute `stories_used` via `Delivery::where('client_id', $id)->where('status', 'sent')->whereMonth('created_at', now()->month)->count()`.
4. Compute `media_used` via `Download::where('client_id', $id)->whereMonth('created_at', now()->month)->count()`.

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Http/Controllers/Api/PortalController.php` (`context()` method, lines 65–87)
- **Reference:**
  - `app/Models/Client.php`, `app/Models/ClientPackage.php`, `app/Models/Delivery.php`, `app/Models/Download.php`
  - `app/Http/Middleware/EnsureClientApiKey.php` (how client is attached)
- **Tests to Modify:**
  - `tests/Feature/PortalApiTest.php` — update `test_context_returns_client_and_saved_searches` to assert real data

---

## 4. Prompt (For the Coding AI)
> Implement M11-PORTAL-001. Replace the hardcoded mock in `PortalController::context()` with dynamic data. Resolve client from API key header if present. Compute real stories_used and media_used counts from deliveries/downloads tables. Get tier from active package, renewal from subscription end_date. Update PortalApiTest to verify real data.

---

## 5. Test Criteria
- [ ] With API key: returns real client name, tier, renewal date
- [ ] Stories and media usage counts match DB records
- [ ] Without API key: returns generic public response
- [ ] Existing `PortalApiTest` passes with updated assertions
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
