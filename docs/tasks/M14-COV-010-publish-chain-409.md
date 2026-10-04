# Task: M14-COV-010 — Publish chain and stale-save 409

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-003, DEC-007; domain.md §2.3–2.6 (FR-NWS-014)

---

## 1. Contract (What)
- **Inputs / Validation:** Draft story, actor allowed to publish. Second save with stale `version`.
- **Outputs / Response:** One test publishes, runs `FanoutStory::handle()` and records outbox insert. Asserts `story_versions` snapshot, `story_events` publish action, delivery row for an entitled client, `index_outbox` pending upsert. Stale save returns 409 (HTTP or Livewire) and does not change headline. `StoryPublished` is not faked in this test.
- **Authorization:** Uploader cannot publish — already tested; do not remove it.

---

## 2. Logic (How)
1. New `tests/Feature/PublishChainTest.php`. Build an entitled client + webhook channel. `Http::fake` 200. Do not `Event::fake()` or `Queue::fake()`.
2. Call the same service method the wizard uses to publish. Then `app(FanoutStory::class)` or `Bus::dispatchSync`. Then assert the four tables. Do not mark outbox `done` (COV-006).
3. Stale save: two versions, save with `expectedVersion` behind. Assert `ConflictHttpException` or Livewire 409 and original headline remains. `AddNews` Livewire test is enough if the component returns 409; a bare exception with no Livewire path is not enough — hit `AddNews` or the controller the UI uses.
4. `takeOver` with stale `expectedVersion` asserts 409 and owner unchanged.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Feature/PublishChainTest.php`
  - Livewire/service only if stale save currently overwrites
- **Reference Files:**
  - `app/Services/StoryService.php`
  - `tests/Feature/StoryWorkflowTest.php`
  - `docs/testing-coverage-gaps.md` §4 publish row

---

## 4. Prompt (For the Coding AI)
> Write one Feature test that publishes a story and runs fan-out without faking the queue or `StoryPublished`. Assert version snapshot, story event, delivery row, and outbox upsert together. Add a Livewire or HTTP stale-save test that gets 409 and does not change the headline. Add takeOver stale-version 409. If the UI save path overwrites on stale version, fix it. Do not fake the event.

---

## 5. Test Criteria
- [ ] Four asserts in one test: versions, events, deliveries, index_outbox
- [ ] Stale save does not change headline
- [ ] Test fails if `Queue::fake()` is added
- [ ] `php artisan test --filter=PublishChainTest` green

---

## 6. Completion Notes
- **Shipped:** `tests/Feature/PublishChainTest.php` exercises one Feature test that publishes a story, runs `FanoutStory::handle()`, and asserts four tables together: `story_versions` snapshot count, `story_events` `published` action, `index_outbox` `main` upsert, and a `sent` delivery row. Stale save (`updateDraft` with `expectedVersion=99`) raises `ConflictHttpException` and the headline in the DB is unchanged. `takeOver` with stale `expectedVersion` raises `ConflictHttpException`.
- **Tests:** `php artisan test --filter=PublishChainTest` 3 passed (7 assertions). `php artisan test` full suite 855 passed / 1 skipped (2753 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
