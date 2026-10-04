# Task: M13-IMG-001 — WebP conversion + real derivatives pipeline

**Status:** ✅ Completed
**Dependencies:** M13-QA-FIX-004 (intake), M13-QA-FIX-003 (embargo)
**Parent ADR:** COS-6 (scope TBD resolved below), FR-MED-008 (`docs/fr-cross-check-report.md` gap)

---

## 1. Contract (What)
COS-6 asked "where in the pipeline and which image types" — resolved as:
- **Where:** upload time (both desk uploads and TUS intake) — deterministic, CDN-ready outputs stored alongside originals.
- **Which types:** every uploaded **photo** asset (video/undecodable files skipped gracefully) — featured, attachments, and thumbnails all get the same derivative set; the original is never touched.

`GenerateDerivatives` was a stub (path strings, no processing, never dispatched, variant names deviating). Now **real GD processing → WebP**:
- Variants aligned to the client API's variant vocabulary: **`thumb` 400w · `small` 800w · `medium` 1200w · `large` 1600w** (no upscaling past source width), stored at `media/derivatives/{public_id}/{variant}.webp` on the asset's disk, recorded as `{path, width, height}` in `derivatives` (existing `grad`/`r` prototype keys preserved).
- **Dispatched** from `PhotoManager::handleUploads` (desk) and `IntakeService::ingest` (TUS) per asset — FR-MED-007's "derivatives ready" consequence now holds by the time approval runs.
- **Real thumbnails render** in the photo-manager grids + stack children (`MediaAsset::thumbUrl()` background-image with the gradient placeholder as fallback) — closes the "no real derivative thumbnails anywhere" note (FR-MED-001).

---

## 2. Context (Where)
- **Files Modified:** `app/Jobs/GenerateDerivatives.php` (real rewrite), `app/Models/MediaAsset.php` (`thumbUrl()`), `app/Livewire/Admin/PhotoManager.php` (capture + dispatch), `app/Services/Media/IntakeService.php` (dispatch), `resources/views/livewire/admin/photo-manager.blade.php` (3 thumb sites), `tests/Feature/GenerateDerivativesTest.php` (new, 5 tests), `tests/Feature/MediaTest.php` + `tests/Feature/Jobs/JobExecutionTest.php` (stub-era tests upgraded to real bytes)

---

## 3. Test Criteria
- [x] Real JPEG → 4 WebP variants on disk (`getimagesize` type check), thumb 400w, no upscale past source, original untouched
- [x] Small source not upscaled; non-image bytes leave derivatives empty
- [x] Desk upload dispatches `GenerateDerivatives`; TUS intake dispatches it too
- [x] Full `php artisan test` green · parity PASSED

---

## 4. Completion Notes
- **Shipped:** Per §1. Idempotent (skips when `thumb` exists — `JobExecutionTest` skip case preserved). Alpha preserved for transparent PNG/WebP sources. COS-6's WebP ask is satisfied at the derivative layer (originals stay source-format for archival).
- **Tests:** `GenerateDerivativesTest` 5 passed + stub-era 3 updated (one asserted the old `preview` key) — full suite **776 passed, 1 skipped, 0 failures**; `php -l` clean; parity PASSED (no schema change).
- **Live Smoke:** suite boot = migrate+seed clean; derivative generation exercised via GD-real bytes in tests (CI runners have GD).
- **Review:** PR.
