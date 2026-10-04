# Task: DST-006/007 — Distribution log filters + auto-pause client notification

**Status:** ✅ Completed
**Dependencies:** M11-DELIV-004 (delivery queue processor)
**Parent ADR:** FR-DST-006 + FR-DST-007 (`docs/fr-cross-check-report.md` P2 backlog)

---

## 1. Contract (What)
1. **FR-DST-007 — Distribution log filters (spec: client, channel, status, date-range):** `DeliveryRepository::paginateWithFilters` now accepts `clientId`, `channelType`, `dateFrom`, `dateTo` alongside `status`/`search` (payload-hash). `DistributionLog` exposes them (client select, channel-type select, from/to date inputs) with page-reset on change.
2. **FR-DST-006 — auto-pause notifies the client contact:** `ClientRepository::recordChannelFailure` now sends `App\Mail\ChannelPaused` (queued) to the client's `billing_email` (fallback: first `client_users` email) when a channel crosses the auto-pause threshold — previously silent. Guard: notification fires only on the active→paused transition (no repeats while paused).

---

## 2. Context (Where)
- **Files Modified:** `app/Repositories/DeliveryRepository.php`, `app/Livewire/Admin/DistributionLog.php`, `resources/views/livewire/admin/distribution-log.blade.php`, `app/Repositories/ClientRepository.php`, `app/Mail/ChannelPaused.php` (new), `resources/views/mail/channel-paused.blade.php` (new), `tests/Feature/DistributionLogFiltersTest.php` (new, 4 tests)

---

## 3. Test Criteria
- [x] Repository filters: client / channel type / date-range / combined / status
- [x] Livewire filters apply to the log view (row-level assertions)
- [x] Auto-pause sends `ChannelPaused` to billing contact; no repeat while paused
- [x] Full `php artisan test` green

---

## 4. Completion Notes
- **Shipped:** Per §1. Mail styled on the `BackupFailed` template. `dateTo` inclusive end-of-day (component appends `23:59:59`).
- **Tests:** `DistributionLogFiltersTest` 4 passed — full suite green; `php -l` clean; pint clean (pre-commit).
- **Review:** PR (feature branch `feature/dst-log-filters-and-pause-alert`).
