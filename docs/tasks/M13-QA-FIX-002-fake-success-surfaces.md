# Task: M13-QA-FIX-002 — Fake-success surfaces cleanup

**Status:** ✅ Completed
**Dependencies:** M13-SEC-001 (COS-30, FR report P1 #6)
**Parent ADR:** AGENTS.md §10 "No fake success"; `docs/fr-cross-check-report.md` deviations #7

---

## 1. Contract (What)
Six surfaces presented fabricated data as live — each now shows **real ledger data or an honest empty state**:
1. **Admin bulk ZIP** (FR-MED-005) — client-side JSZip fabricated gradient PNGs → real `downloadZip` Livewire action streaming a genuine ZIP of originals (+ `captions.txt` manifest; missing originals noted, never faked). Client-side JSZip block removed.
2. **AP sync** (FR-MED-014) — `todaySyncCount` 214→0, `syncNow()` no longer increments or claims success (honest "not configured" toast), `syncLogs` demo rows → `[]`, `totalApCount` `max(1248, …)` floor → real count.
3. **Client usage analytics** (FR-MED-017) — fabricated Daily Star/Prothom Alo/bdnews24 percentages → real per-client counts from the `downloads` ledger (queried in `selectAsset`).
4. **AI usage bars** (FR-AI-007) — 188200/97600/26600 token fallbacks + fake call counts 418/261/89 → real rollups (zeros when empty); calls from `ai_generations` joined `stories.language`.
5. **License history** (FR-MED-015) — demo rows fallback removed → empty when ledger empty; `downloaded_by` demo string → `N/A`.
6. **Client 360 stats** (FR-CLT-004) — usage from `clients.notes` mock → real `downloads` counts, `client_api_keys.last_used_at`, `QuotaService` quota, package-derived tier (notes JSON kept for display fields, `usage`/`tier` always real).

---

## 2. Context (Where)
- **Files Modified:** `app/Livewire/Admin/{PhotoManager,ApPhotoManager,AiSettings,DeliverySettings}.php`, `app/Services/ClientService.php`, `resources/views/livewire/admin/photo-manager.blade.php` (JSZip block removed, `wire:click="downloadZip"`, real usage rows), `tests/Feature/{PhotoManager,ApPhotoManager,DeliverySettings,AiSettings}Test.php`

---

## 3. Test Criteria
- [x] Bulk ZIP streams a real archive (`assertFileDownloaded('unb-photos-1.zip')`)
- [x] AP sync honest (count 0, no fake log rows)
- [x] License history empty without ledger rows; renders + CSV with rows
- [x] AI stats canonical zeros without usage (was enshrining 312400/4186)
- [x] Full `php artisan test` green · `php -l` clean

---

## 4. Completion Notes
- **Shipped:** Per §1. Two tests that enshrined the fabrication were rewritten (`test_sync_now…`, `test_download_history…`) + `AiSettingsTest` canonical-defaults asserts updated to zeros. `test_toggle_sync_log` now asserts the fake rows are GONE.
- **Tests:** +2 new (ZIP stream, honest-empty license history) — full suite **752 passed, 1 skipped, 0 failures**; `php -l` clean; parity PASSED (no schema change).
- **Live Smoke:** `optimize:clear`; `monitor:outbox-lag` OK lag=0s; route smoke (feed/admin auth redirect) — page renders covered by feature tests (500-guards included).
- **Review:** PR.
