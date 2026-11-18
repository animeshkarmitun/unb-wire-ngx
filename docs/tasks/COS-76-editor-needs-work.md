# Task: COS-76 — Editor "Needs work": send an in_review story back to the uploader with a comment

**Status:** 🔧 In Progress
**Priority:** P2 (core newsroom workflow broken)
**Linear:** [COS-76](https://linear.app/cosmosgroupcom/issue/COS-76/workflow-editor-cannot-send-a-story-back-to-the-uploader-needs)
**Affected surfaces:** AddNews wizard desk-workflow strip (`add-news-stepper.blade.php`), AddNews Livewire component
**Parent contracts:** FR-NWS-010 lifecycle (`in_review → changes_requested`), M10-HIST-004 event vocabulary, FR-NWS-012 internal notes

---

## 1. Issue Summary

Editors only had **"Take over this story"** — no way to return an in_review story to the
uploader with a comment, so the News list's "Needs work" tab could never be used.

## 2. Pre-existing plumbing (already in place)

- `StoryService::TRANSITIONS` allows `in_review → changes_requested` and
  `changes_requested → in_review` (resubmit).
- The News list already has the "Needs work" tab/filter/pill wired to
  `changes_requested`.
- `StoryService::transition()` already notifies the owner on `changes_requested`
  (email supported by `StoryNotification`).
- The Story **View** page had a "Request Edit" button; the **wizard** (where editors
  actually review) did not.

## 3. Implementation

- `AddNews::requestChanges()` (new): `assertCan('stories','edit')`, requires a comment
  (`needsWorkComment`, min 5 chars), guards `status === 'in_review'` and
  `owner !== actor`, then `transition(changes_requested)` + `NoteService::add`
  (internal note; `note_added` event; owner notified).
- `AddNews::toggleNeedsWorkForm()` + inline comment form in the desk-workflow strip.
- "Needs work" button visible in the wizard only for **non-owner + in_review**.
- `publish()` walk now includes the missing `changes_requested → in_review` leg so a
  publish-capable user finishing rework can publish without a silent no-op.

## 4. Verification Plan

- [x] New tests (AddNewsTest): editor send-back with comment (events `changes_requested`
      + `note_added`, internal note row), comment required, owner cannot send back,
      button visibility (editor sees / owner doesn't), publish from `changes_requested`.
- [x] `php artisan test` — **1001 passed / 0 failed**; `php -l` clean.
- [ ] Live smoke: Enamul → in_review → Ruma "Needs work" + comment → Enamul resubmits.

## 5. Related findings from the same QA pass (out of scope here)

- Page title says "Add News" when editing an existing story (cosmetic).
- "Restore draft" mixes users' browser-local drafts on shared PCs (draft storage is
  browser-local by design — needs a product decision, tracked separately).
- Uploader "Publish now" hint — uploaders are already blocked server-side
  (`stories,publish`); the hint text could be role-aware (cosmetic).

## 6. Completion Notes

_(pending)_
