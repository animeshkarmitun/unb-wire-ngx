# Task: M13-QA-FIX-004 — Media ingest pipeline (TUS byte storage + intake hand-off)

**Status:** ✅ Completed
**Dependencies:** M13-QA-003 (embargo enforcement), M13-QA-001 (P0 #2)
**Parent ADR:** FR-MED-006/009; `docs/fr-cross-check-report.md` P0 #2 (last open P0)

---

## 1. Contract (What)
The media ingest pipeline was a facade — production ingest was impossible:
1. **`TusController::patch` stored no bytes** (chunk measured then discarded). Now: chunk bytes are written to `uploads/{session_id}` on the local disk at the declared offset (seek + write, `c+b`), offset tracking unchanged, session completes when offset reaches length.
2. **No intake hand-off** — completed sessions never became `media_batches`/`media_assets`; the FR-MED-006 intake queue was `MediaSeeder`-only. Now: on completion `IntakeService::ingest` creates a `pending` `MediaBatch` (+ uploader, event label from filename, `routine` urgency, `submitted_at`) and a `field`-status `MediaAsset` (`source=field`, sha256 checksum, mime/size, original moved to `media/uploads/{ulid}.{ext}` on the `public` disk — matching the desk-upload convention). `getPendingBatches` / PhotoManager intake then work from real uploads. Re-ingest guard: only `active` → `completed` transitions trigger ingest (no duplicate batches on stray extra patches).

---

## 2. Context (Where)
- **Files Created/Modified:** `app/Services/Media/IntakeService.php` (new), `app/Http/Controllers/TusController.php` (byte storage + completion hook), `tests/Feature/MediaIngestPipelineTest.php` (new, 4 tests)

---

## 3. Test Criteria
- [x] Patch stores real bytes (asserted file content) + completion hands off batch+asset to the intake queue (checksum + `field` source verified)
- [x] Chunked resumable upload reassembles bytes in order (HEAD offset round-trip)
- [x] Partial upload stays `active`, no rows yet
- [x] Extra patch after completion does not double-ingest
- [x] `TusUploadTest` (8) stays green · full `php artisan test` green

---

## 4. Completion Notes
- **Shipped:** Per §1. Field intake queue (FR-MED-006) is now fed by real production uploads; PhotoManager approve/reject/re-edit flows consume them unchanged.
- **Tests:** `MediaIngestPipelineTest` 4 passed + `TusUploadTest` 8 green — full suite **771 passed, 1 skipped, 0 failures**; `php -l` clean; parity PASSED (no schema change).
- **Live Smoke:** suite boot = migrate+seed clean; TUS routes exercised by the API tests (401/403/409/ownership included).
- **Review:** PR.
