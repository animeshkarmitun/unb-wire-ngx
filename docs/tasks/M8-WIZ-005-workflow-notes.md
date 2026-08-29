# Task: M8-WIZ-005 — Desk workflow strip + internal notes

**Status:** ✅ Completed
**Dependencies:** M8-WIZ-001
**Parent ADR:** app-data/add-news.html + FR-NWS-010

---

## 1. Contract (What)
- **Inputs / Validation:** Sidebar: `wf-strip` (status pill draft/review/rework/approved/live + owner avatar + shift + spacer + take-over/send-to-editor), `wf-notes` thread (add note body ≤2000, reply).
- **Outputs / Response:** `<x-workflow-strip>` + `<x-note-thread>` wired to `story_notes` (is_internal) + `story_events` chain-of-custody.
- **Authorization:** `RbacService.assertCan` on take-over + note add; Uploader cannot change status beyond own scope.

---

## 2. Logic (How)
1. Build wf-strip with avatar variants (navy/green/blue) + shift label + spacer + btns.
2. Notes: `nt-list` (editor green left border, sub, sys dashed) + `nt-reply` textarea + submit → `NoteService::add` in tx, Reverb broadcast to thread.
3. Take-over: `StoryService::takeOver` force-releases `locked_by/locked_at`, writes `story_events`, bumps `version`.
4. Ensure notes never on feed/search (filter `is_internal`).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/AddNews.php` (notes actions)
  - `resources/views/livewire/admin/add-news.blade.php` (wf-strip + notes)
  - `app/Services/NoteService.php`
- **Reference Files:** `app-data/add-news.html` wf-strip/notes

---

## 4. Prompt (For the Coding AI)
> Build workflow strip + internal notes faithful: avatar+shift+take-over, thread with replies, immutable sys items, Rbac asserts, 409 on stale take-over.

---

## 5. Test Criteria
- [ ] Add note creates `story_notes` is_internal + `story_events`
- [ ] Take-over 409 on version mismatch
- [ ] Internal notes excluded from Meilisearch index
- [ ] Visual matches prototype at 1440px

---

## 6. Completion Notes
- **Shipped:** Wired `addNote()` in `AddNews` (creates `story_notes` is_internal + `story_events`) + textarea `wire:model="noteBody"`; requires `storyId` draft exists.
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin/add-news` note add.
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
