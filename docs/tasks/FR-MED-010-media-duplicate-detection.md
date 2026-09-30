# Task: FR-MED-010 — Media duplicate detection (checksum lookup on ingest)

**Status:** ✅ Completed
**Dependencies:** M13-QA-FIX-004 (ingest), M13-IMG-001 (derivatives)
**Parent ADR:** FR-MED-010 (`docs/fr-cross-check-report.md` P2 — was "missing")

---

## 1. Contract (What)
Checksums were computed and stored but never consulted. Now every ingest checks for an existing asset with the same checksum and **warns with a link to the existing asset**:
- `MediaRepository::findDuplicateOf(MediaAsset)` — earliest other asset sharing the checksum; `duplicateFlags(array $ids)` — batch map for queues.
- **Desk uploads** (`PhotoManager::handleUploads`): per-file duplicate check → "Possible duplicates detected — verify before publishing" banner (root-level, with per-item **"Open existing"** buttons wired to `selectAsset`) + a toast.
- **Field intake queue:** assets whose checksum matches anything outside their batch get a **"Possible duplicate"** badge (click → inspector).

---

## 2. Context (Where)
- **Files Modified:** `app/Repositories/MediaRepository.php` (`findDuplicateOf`, `duplicateFlags`), `app/Livewire/Admin/PhotoManager.php` (`uploadDuplicates` state + check in `handleUploads` + `duplicateIds` in render), `resources/views/livewire/admin/photo-manager.blade.php` (root banner + intake badge), `tests/Feature/MediaDuplicateTest.php` (new, 4 tests)

---

## 3. Test Criteria
- [x] `findDuplicateOf` matches checksum / returns null on unique
- [x] Desk upload warns with the existing asset's title+id (link target)
- [x] Clean upload sets no warnings; intake queue marks duplicates
- [x] Full `php artisan test` green

---

## 4. Completion Notes
- **Shipped:** Per §1. Detection is non-blocking (spec: "duplicate **warning**") — the editor decides.
- **Root-cause note:** first test iteration saw empty state because `WithFileUploads`' `updatedUploads()` hook **auto-runs `handleUploads()` on `set('uploads',…)`** — an explicit second `call('handleUploads')` re-ran it with the already-reset `uploads=[]`. Tests now trigger via `set()` only.
- **Tests:** `MediaDuplicateTest` 4 passed — full suite green; `php -l` clean.
- **Review:** PR.
