# Task: M9-MED-001 — Media Review & Field Intake Queue Pipeline

**Status:** ✅ Complete
**Dependencies:** M9-MED (Photo Management), M12-NTF-004 (Media Decision Notifications)

---

## 1. The Contract (What)

### 1.1 Field Intake Queue (`/admin/photos?tab=field`)
- Displays batches awaiting desk review (`MediaBatch::where('status', 'pending')->whereHas('assets')`).
- Each batch displays:
  - Photographer avatar, name (`Mim Akter`), city, relative time ago.
  - Event label (`Sylhet flood relief field photos`).
  - Urgency badge (`urgent`, `breaking`, `routine`).
  - Frame count badge.
- Photo frames inside batch (`.fq-ph`):
  - Thumbnail gradient placeholder.
  - Hover action: Approve frame (`.fq-pact.ok`).
  - Hover action: Reject frame (`.fq-pact.no`).
  - Caption and photographer credit meta.
- Batch action footer (`.fq-bfoot`):
  - Approve all frames (`.fq-btn.ok`).
  - Request re-edit (`.fq-btn.warn`).
  - Reject batch (`.fq-btn.danger`).

### 1.2 Review Decisions, Ledgering & Notifications
- **Approve Single Asset**:
  - Sets `media_assets.status = 'library'`, `approved_at = now()`, `approved_by = $reviewerId`.
  - Auto-assigns package if not present.
  - Records `media_reviews` row: `action: 'approve'`, `reviewer_id`, `asset_id`.
  - Dispatches `media_approved` notification to uploader.
- **Approve Entire Batch**:
  - Iterates assets: sets each to `library`, records `media_reviews` entry.
  - Sets `media_batches.status = 'reviewed'`, `reviewed_by`, `reviewed_at`.
  - Dispatches `media_approved` notification to uploader.
- **Request Re-Edit (`openModal('reedit_batch')`)**:
  - Displays modal `#fqOverlay` with title containing `Request re-edit`.
  - Displays selectable reasons (e.g., `Captions need names / places filled in`, `Crop tighter on the lead frames`).
  - Provides optional custom note textarea `#fqNote`.
  - On confirm: transitions batch assets to `reedit`, batch to `reviewed`.
  - Writes `media_reviews` row with `action: 'reedit'`, `reason_code`, and `note`.
  - Dispatches `media_reedit` notification to uploader.
- **Reject Photo / Batch (`openModal('reject_photo')` / `openModal('reject_batch')`)**:
  - Displays modal `#fqOverlay` with title containing `Reject`.
  - Displays selectable rejection reasons (e.g., `Out of focus / soft at full size`, `Weak composition — not publishable`).
  - On confirm: sets target asset/assets status to `rejected`.
  - Writes `media_reviews` row with `action: 'reject'`, `reason_code`, and `note`.
  - Dispatches `media_rejected` notification to uploader.

### 1.3 Sticky Inspector Panel (`#inspPanel`)
- Clicking any asset in the photo grid opens `#inspPanel`.
- Editable fields:
  - Caption (`#iCap`).
  - Photographer (`#iBy`).
  - Location (`#iLoc`).
  - Keywords IPTC (`#iKw`).
  - Package assignment (`#iPkg`).
  - Story linking (`#iStory`, `#iLink`).
  - Library approval button (`#iApprove`).
- `#iSave`: Persists changes to `media_assets` and package relations; dispatches toast `✓ Metadata saved`.

### 1.4 Bulk Action Bar (`#bulkbar`)
- Appears when photos are checked in grid.
- Shows selected count `#bulkCount`.
- Bulk approve to library `#bulkApprove`.
- Bulk package assignment `#bulkPkg`.
- Bulk clear selection `#bulkClear`.

### 1.5 Uploader Notification Verification
- Field photographer (`mim@unbnews.org`) logs into `/admin/notifications`.
- Receives notifications with matching labels (`Media approved`, `Media rejected`, `Re-edit requested`).
- Notification deep link directs back to `/admin/photos`.

---

## 2. Success Criteria & Verification Plan

1. **Seed Fixture (`seed-data.php media`)**:
   - Seeds pending batch for `Mim Akter` with 2 field photos.
2. **Playwright Tests (`tests/e2e/media-review-pipeline.spec.ts`)**:
   - Test 1: Field intake queue renders pending batches, urgency badge, photos, uploader name.
   - Test 2: Approve single photo moves asset to library, records `media_reviews` row, shows toast.
   - Test 3: Request re-edit opens modal, selects reason, inputs note, transitions batch to re-edit, verifies `media_reviews` row.
   - Test 4: Reject photo opens modal, records rejection reason code, transitions asset to rejected.
   - Test 5: Sticky inspector edits caption, photographer, location, keywords, and package, persists to DB.
   - Test 6: Bulk select photos, assign package, and bulk approve to library.
   - Test 7: Photographer (`Mim Akter`) sees desk review decision notifications in notification center.
