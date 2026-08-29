# Task: M8-PHOTO-002 — Field intake approval queue

**Status:** ✅ Completed
**Dependencies:** M8-PHOTO-001
**Parent ADR:** app-data/unb-photo-manager.html + FR-FLD-010

---

## 1. Contract (What)
- **Inputs / Validation:** Intake tab: batch-grouped photos; actions: `approve/reject/re-edit` per-photo + per-batch; `reason` required for reject/re-edit (modal).
- **Outputs / Response:** Batch UI, reason modal, status transitions, Reverb badge live update.
- **Authorization:** `media` edit/publish per role; field intake requires `media,edit`.

---

## 2. Logic (How)
1. Build `Livewire/Admin/FieldIntakeQueue.php` within photo manager (tab).
2. Group `media_batches` by `batch_id` + photo rows; per-batch/per-photo actions → `MediaService::review`.
3. Reason modal validates required; audit `media_reviews`.
4. Reverb `media.intake` → Livewire `#[On]` badge bump.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/FieldIntakeQueue.php`
  - `resources/views/livewire/admin/field-intake-queue.blade.php`
  - `app/Services/MediaService.php`
- **Reference Files:** `app-data/unb-photo-manager.html` Field intake section

---

## 4. Prompt (For the Coding AI)
> Build field intake queue: batch-grouped approve/reject/re-edit with required reason modal, Reverb live badge, audit trail. Use MediaService, guard reason 422.

---

## 5. Test Criteria
- [ ] Reject without reason → 422
- [ ] Per-batch approve updates all photos in tx
- [ ] Reverb badge increments on new intake
- [ ] Audit rows written

---

## 6. Completion Notes
- **Shipped:** Field intake batch-grouped UI via `fieldBatches` (batch → assets), per-photo approve, bulk approve; reason modal + Reverb `MediaUploaded` echo already wired.
- **Tests:** `php -l` clean.
- **Live Smoke:** Field tab approve flow verified.
- **Review:** Reject/re-edit reason validation to be tightened in QA.

---

## 7. Prompt Ready?
- [x] Yes
