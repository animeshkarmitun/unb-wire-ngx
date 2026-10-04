# Task: M14-COV-005 — Kill/correction pipeline + index delete

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; domain.md §2.7 (FR-DST-008, FR-NWS-016)

---

## 1. Contract (What)
- **Inputs / Validation:** Published story that already has a `sent` delivery. Actor with publish permission calls kill. Separate case: published story corrected via `updateDraft`.
- **Outputs / Response:** Kill writes `story_events`, enqueues `index_outbox` `op=delete` on `main` for that `public_id`, and `FanoutStory` sends `story.killed` only to prior recipients. A client who never received it gets no kill notice. Correction dispatches fan-out with `story.updated` and an outbox upsert, not a delete.
- **Authorization:** Existing `StoryService` publish/kill permission. Test the deny path already covered; do not add a new role.

---

## 2. Logic (How)
1. One Feature test calls `StoryService::transition(..., 'killed')` then `FanoutStory::handle()`. Assert delivery row event/payload, no notice to a never-received client, and an `index_outbox` delete row. If kill does not insert that delete row, add the insert in the same transaction as the status change.
2. Correction test: published story `updateDraft` → outbox upsert, fan-out `story.updated`, prior recipient only if that is current `hasPriorSuccess` behavior. Do not re-check entitlement for corrections in this task (COV-004 owns entitlement). If COV-004 has landed, do not undo it.
3. Feed tombstone test stays in `FeedTombstoneTest`. This task links kill → outbox delete. Do not require Meilisearch to be up.
4. Do not implement `ProcessIndexOutbox` delete handling here (COV-006).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Services/StoryService.php` (index delete on kill/unpublish only if missing)
  - `tests/Feature/FanoutAdvancedTest.php` or new `tests/Feature/KillPipelineTest.php`
- **Reference Files:**
  - `docs/testing-coverage-gaps.md` §4 kill row
  - `tests/Feature/Api/FeedTombstoneTest.php`

---

## 4. Prompt (For the Coding AI)
> Add one integration test: kill a published story that one client received, run `FanoutStory`, assert a kill notice only for that client and an `index_outbox` main delete. If the service does not write the delete, add it in the kill transaction. Add a correction test that writes an upsert, not a delete. Do not process the outbox job (that is COV-006). Do not weaken kill tests that already exist.

---

## 5. Test Criteria
- [ ] Kill without prior delivery creates no kill notice
- [ ] Kill with prior delivery creates one notice and one main delete outbox row
- [ ] Correction does not insert `op=delete`
- [ ] `php artisan test --filter=KillPipeline` (or the file you add) green

---

## 6. Completion Notes
- **Shipped:** `app/Services/StoryService.php` — kill transition now writes an `index_outbox` `op=delete` row on `main` for the killed story's `public_id` (in the same transaction as the status change). `updateDraft` on a published story now writes an `op=upsert` row, so corrections feed the index. New `tests/Feature/KillPipelineTest.php` covers: kill without prior delivery creates no notice; kill with prior delivery creates a notice for that client (and main delete outbox row) but not for a never-entitled client; correction writes upsert only.
- **Tests:** `php artisan test --filter=KillPipelineTest` 3 passed (7 assertions). `php artisan test` full suite 821 passed / 1 skipped (2671 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
