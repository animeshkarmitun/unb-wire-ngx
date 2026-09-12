# Task: M11-PORTAL-003 — Real presigned media downloads (replace fake canvas)

**Status:** ⏳ Pending
**Dependencies:** FIX-003 (client download route), M11-PORTAL-002 (auth)
**Parent ADR:** FR-DST-002

---

## 1. Contract (What)
- **Inputs:** Media asset ID + variant (original/thumbnail/web) + client API key
- **Outputs:** Real S3 presigned download URL → browser downloads actual media file
- **Current state:** Portal generates fake gradient PNGs via HTML5 canvas (`gradientPng()` in `portal/lib/format.ts`), video download shows fake "FTP auto-push" toast

---

## 2. Logic (How)
1. Update `portal/app/page.tsx` download handlers:
   - Replace `gradientPng(...)` + `saveBlob(...)` calls with fetch to `GET /api/v1/media/{id}/download?variant=original` (includes `X-API-Key` header).
   - On response: redirect browser to `response.url` (the presigned S3 URL).
2. Update `portal/lib/format.ts`:
   - Keep `gradientPng()` as fallback for demo/unauthenticated mode.
   - Add `downloadMedia(assetId, variant, apiKey)` function that calls the real endpoint.
3. Handle download progress/toast for large files.
4. Remove fake "FTP auto-push" toast for video downloads.
5. Fix all download code paths (search for `gradientPng` — found at lines 392, 443, 2498, 2593, 3025, 3129).

---

## 3. Context (Where)
- **Files to Modify:**
  - `portal/app/page.tsx` (6 gradientPng call sites)
  - `portal/lib/format.ts` (add `downloadMedia()` helper)
- **Reference:**
  - `routes/api.php` line 19: `GET /api/v1/media/{id}/download` (added by FIX-003)
  - `app/Http/Controllers/Api/MediaController.php` (`clientPresigned()`)
- **Tests:**
  - Update `tests/e2e/portal-ui.spec.ts` — verify download initiates API call (mock response)

---

## 4. Prompt (For the Coding AI)
> Implement M11-PORTAL-003. Replace all 6 `gradientPng()` call sites in portal/app/page.tsx with real API calls to `/api/v1/media/{id}/download`. Create `downloadMedia()` helper in portal/lib/format.ts. Keep gradientPng as fallback for unauthenticated/demo mode. Remove fake "FTP auto-push" video toast. Update E2E tests.

---

## 5. Test Criteria
- [ ] Authenticated user clicks download → API call to `/api/v1/media/{id}/download`
- [ ] Browser redirects to presigned S3 URL
- [ ] Guest mode falls back to demo gradient (no 401 errors)
- [ ] All 6 gradientPng call sites updated
- [ ] No fake "FTP auto-push" toast for video
- [ ] E2E test verifies download flow

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
