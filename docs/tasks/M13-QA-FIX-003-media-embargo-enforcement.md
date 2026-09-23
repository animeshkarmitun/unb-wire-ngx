# Task: M13-QA-FIX-003 — Media embargo enforcement at delivery edges

**Status:** ✅ Completed
**Dependencies:** M13-QA-001 (FR report P0 #3), M13-QA-FIX-001
**Parent ADR:** FR-MED-016; `docs/fr-cross-check-report.md` P0 #3

---

## 1. Contract (What)
`media_assets.embargo_until` was **display-only** — embargoed assets leaked through every client-facing edge. Now:
1. **Enforcement (hide existence — 404/exclude) at all client-facing edges:** client presigned download (`MediaController::clientPresigned`), client ZIP export (`MediaController::export` + `ExportMediaZipJob` + belt-and-braces filter inside `MediaZipExportService::export`), fan-out payloads (`WebhookPayloadBuilder`), wire format output (`JsonUnbV1Formatter`, `NewsmlG2Formatter`, `NitfFormatter`), portal feed/story serialization (`PortalController`). **Staff paths keep access** (desk review) — `/api/media/{id}/presigned` unchanged.
2. **Set-embargo UI:** PhotoManager inspector "Embargo until (client hold)" datetime field, persisted via `saveAssetMetadata` (Dhaka-tz input, stored UTC).
3. **Expiry is automatic** — `clientVisible` scope is query-time (`embargo_until IS NULL OR embargo_until <= now()`), so no lift job is needed (refines the report's lift-job note).

---

## 2. Logic (How)
1. `MediaAsset::isEmbargoed()` helper + `scopeClientVisible()` (model).
2. Client queries append `clientVisible()` so embargoed rows simply "don't exist" for clients (404 semantics without leaking).
3. Serializer/formatter media lists run `->reject(fn ($m) => $m->isEmbargoed())->values()` (values() keeps JSON arrays — the reject preserves keys otherwise).
4. `MediaZipExportService::export` rejects embargoed defensively for any caller.

---

## 3. Context (Where)
- **Files Modified:** `app/Models/MediaAsset.php`, `app/Http/Controllers/Api/MediaController.php`, `app/Http/Controllers/Api/PortalController.php`, `app/Jobs/ExportMediaZipJob.php`, `app/Services/Media/MediaZipExportService.php`, `app/Services/Delivery/WebhookPayloadBuilder.php`, `app/Services/Delivery/Formats/{JsonUnbV1Formatter,NewsmlG2Formatter,NitfFormatter}.php`, `app/Livewire/Admin/PhotoManager.php` (+ Carbon), `resources/views/livewire/admin/photo-manager.blade.php`, `tests/Feature/MediaEmbargoTest.php` (new, 7 tests)

---

## 4. Test Criteria
- [x] Client download embargoed → 404; expired → 200 (auto-visible); staff → 200
- [x] ZIP export excludes embargoed (asset_count reflects visible only)
- [x] Webhook payload + wire JSON formatter exclude embargoed media
- [x] Inspector save sets + clears `embargo_until`
- [x] Full `php artisan test` green · `php -l` clean · parity PASSED

---

## 5. Completion Notes
- **Shipped:** Per §1/§2. Found + fixed along the way: `reject()` preserves collection keys → added `->values()` (payload media arrays would have serialized as JSON objects).
- **Tests:** `MediaEmbargoTest` 7 passed — full suite **767 passed, 1 skipped, 0 failures**; parity PASSED (no schema change).
- **Live Smoke:** suite boot = migrate+seed clean; media routes covered by the new API tests (guest/keys/staff paths).
- **Review:** PR.
