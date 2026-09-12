# Task: M12-NTF-004 — Wire media intake decision notifications

**Status:** ✅ Complete
**Dependencies:** M12-NTF-001 (NotificationService pattern established)
**Parent ADR:** FR-MED-006, FR-MED-007, FR-NTF-001

---

## 1. Contract (What)
- **Inputs:** Media batch/asset + decision (approved/rejected/re-edit) + reviewer — already available in `PhotoManager` approval flow
- **Outputs:** In-app notification sent to the **uploader** (batch creator)
- **Events:**
  | Decision | Event name |
  |----------|-----------|
  | Approved | `media_approved` |
  | Rejected | `media_rejected` |
  | Re-edit requested | `media_reedit` |
- **Burst key:** `media:{batchId}` — collapses per batch per 5 minutes
- **Authorization:** N/A (internal service call)

---

## 2. Logic (How)
1. Locate the media approval/rejection flow in `PhotoManager` Livewire component (or a dedicated service if one exists).
2. After the approval/rejection action, resolve the uploader from `media_batch.created_by` or `media_asset.uploaded_by`.
3. Call `NotificationService::notifyMediaDecision($batchId, $decision, $actorId, $uploader)`.
4. Add `notifyMediaDecision` method to `NotificationService`:
   ```php
   public function notifyMediaDecision(int $batchId, string $decision, int $actorId, User $uploader): void
   {
       $this->burstNotify('media:'.$batchId, collect([$uploader]), $decision, [
           'batch_id' => $batchId, 'actor_id' => $actorId,
       ]);
   }
   ```
5. Update topnav color map: `media_approved` → green, `media_rejected` → crimson/red, `media_reedit` → amber.

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Livewire/Admin/PhotoManager.php` (or relevant service) — add notification call after approval/rejection
  - `app/Services/NotificationService.php` — add `notifyMediaDecision` method
  - `resources/views/components/topnav.blade.php` — extend event color map
- **Reference Files:**
  - `app/Models/MediaBatch.php`, `app/Models/MediaAsset.php`
- **Tests:**
  - `tests/Feature/MediaDecisionNotificationTest.php` — assert uploader notified on approve/reject/re-edit, reviewer not self-notified

---

## 4. Prompt (For the Coding AI)
> You are the Coder. Implement task M12-NTF-004.
>
> 1. Read `app/Livewire/Admin/PhotoManager.php` to find where approve/reject/re-edit actions happen.
> 2. Add `notifyMediaDecision(int $batchId, string $decision, int $actorId, User $uploader)` to `NotificationService`.
> 3. After each approval/rejection action, resolve the batch uploader and call `notifyMediaDecision`.
> 4. Update `topnav.blade.php` event color ternary to include `media_approved` (green), `media_rejected` (crimson), `media_reedit` (amber).
> 5. Write `tests/Feature/MediaDecisionNotificationTest.php`:
>    - `test_approve_notifies_uploader`
>    - `test_reject_notifies_uploader`
>    - `test_reedit_notifies_uploader`
> 6. Run `php -l` on all changed files, then `php artisan test --filter=MediaDecisionNotification`.

---

## 5. Test Criteria
- [ ] Uploader receives `media_approved` notification on approval
- [ ] Uploader receives `media_rejected` notification on rejection
- [ ] Uploader receives `media_reedit` notification on re-edit request
- [ ] Reviewer does not receive self-notification
- [ ] Burst dedup works per batch within 5-min window
- [ ] Topnav renders correct color dots for media events
- [ ] `php -l` clean on all modified files

---

## 6. Completion Notes
*(filled on completion)*

---

## 7. Prompt Ready?
- [x] Yes
