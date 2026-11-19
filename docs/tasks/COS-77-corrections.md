# Task: COS-77 — Corrections visible to clients (`corrected_at` + `is_corrected`)

**Status:** 🔧 In Progress
**Priority:** P3 (wire-service correction marking)
**Linear:** [COS-77](https://linear.app/cosmosgroupcom/issue/COS-77/dist-corrected-stories-are-not-marked-no-updated)
**Affected surfaces:** stories schema, `StoryService::updateDraft`, portal feed/detail payloads
**Parent contracts:** Wire-service correction convention; FR-NWS revision chain

---

## 1. Issue Summary

A published story edited after publication reached clients with the new text but no
`updated_at`, correction flag or notice — a newsroom that ran the old copy couldn't tell
it changed.

## 2. Fix

- New `stories.corrected_at` (nullable timestamptz) migration.
- `StoryService::updateDraft` sets `corrected_at = now()` when the story is
  `published` at save time (draft edits don't).
- Portal `feed` + `story/{id}` payloads expose `corrected_at` (ISO8601) and
  `is_corrected: bool`.

## 3. Verification

- [x] `StoryCorrectionTest` (3 tests): published edit sets `corrected_at`; draft edits
      don't; portal payloads expose the flag.
- [x] `php artisan test` — **1029 passed / 0 failed**; pint clean; schema parity PASSED.
- [x] **Live:** migration run on the server; a real published story corrected via
      `updateDraft` → client API payload returns
      `is_corrected: true`, `corrected_at: 2026-11-19T08:32:05+00:00`,
      `published_at` unchanged (2026-11-17). Exactly the QA expectation.

## 4. QA side-questions answered (in the Linear comment)

- **Kill/withdrawal notices:** implemented — `FanoutStory::sendKillNotices` creates
  `story.killed` deliveries to every client/channel that previously received the story.
- **Body-leading `[TEST]` stripped on save:** intentional — `HtmlSanitizer` strips
  bracketed markers as unsafe/plain-text artefacts. Not a bug fix.

## 5. Completion Notes

_(pending)_
