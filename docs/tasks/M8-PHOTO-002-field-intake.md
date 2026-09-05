# Task: M8-PHOTO-002 — Field Intake Approval Queue faithful

**Status:** ✅ Completed
**Dependencies:** M8-PHOTO-001
**Parent ADR:** app-data/unb-photo-manager.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/photos?tab=field` `auth,verified,rbac:media,view`. Actions: `approveFieldAsset($id)`, `approveFieldBatch($batchId)`, `openModal($type, $batchId, $assetId)`, `confirmModalAction()`.
- **Outputs / Response:** Queue summary with photo/batch totals and live watching pulse, batch cards grouped by event with photographer avatar initials, location, wait time, event badge, urgency pill (`breaking`, `urgent`, `normal`), photo cards with hover quick actions (`.ok` approve to library, `.no` reject with modal), batch footer buttons (`Approve all N`, `Request re-edit…`, `✕ Reject batch…`), and 460px decision modal with preset radio reasons (`REJECT_REASONS` / `REEDIT_REASONS`) and note textarea.
- **Audit & Invariants:** Every decision writes an immutable `media_reviews` row (`action`, `reason_code`, `note`, `reviewer_id`) per `DEC-007` and NFR §8.

---

## 2. Logic (How)
1. `PhotoManager::render()` queries `MediaBatch::with('assets')` where status is pending and orders by `submitted_at DESC`.
2. `approveFieldAsset($id)` marks asset as library, assigns package if needed, and records `MediaReview`.
3. `approveFieldBatch($batchId)` approves all photos in batch, marks batch reviewed, and records `MediaReview` per asset.
4. Decision modal supports 4 preset reject reasons and 4 preset re-edit reasons plus optional custom note. Submitting updates asset/batch statuses to `rejected` or `reedit` and logs audit reviews.

---

## 3. Context (Where)
- **Files Modified:**
  - `app/Livewire/Admin/PhotoManager.php`
  - `resources/views/livewire/admin/photo-manager.blade.php`
  - `resources/css/photo-manager.css`
  - `database/seeders/MediaSeeder.php`
  - `tests/Feature/PhotoManagerTest.php`
  - `tests/e2e/photo-manager-faithful.spec.ts`

---

## 4. Test Criteria
- [x] Field intake queue displays batch summary with live pulse
- [x] Batches group photos by photographer and event with wait time and urgency badges
- [x] Single photo hover actions allow quick approval or reject modal launch
- [x] Batch footer provides `Approve all`, `Request re-edit…`, and `Reject batch…`
- [x] Decision modal renders 4 preset reasons and note textarea, persisting `MediaReview` records with action and reason
- [x] Automated PHPUnit tests pass (`PhotoManagerTest`: 7/7 passed)
- [x] Automated Playwright E2E tests pass (`photo-manager-faithful.spec.ts`: 4/4 passed)

---

## 5. Completion Notes
- **Shipped:** Complete desk-side approval queue with batch grouping, urgency badges, hover approve/reject quick actions, batch footer actions, and 460px decision modal with `MediaReview` audit logging.
- **Tests:** `php artisan test --filter=PhotoManagerTest` (7 passed, 51 assertions).
- **E2E:** `npx playwright test tests/e2e/photo-manager-faithful.spec.ts` (4 passed).
- **Live Smoke:** Tested live on `/admin/photos?tab=field` with seeded batches.
