# Task: M8-NEWS-002 — Workflow drawer (status flow + take-over + notes)

**Status:** ✅ Completed
**Dependencies:** M8-NEWS-001
**Parent ADR:** app-data/english-news.html + FR-NWS-010

---

## 1. Contract (What)
- **Inputs / Validation:** Row click → `WorkflowDrawer` with story `public_id`; transitions: `draft→in_review→changes_requested→approved→published (+ killed, archived)`; `takeOver` requires `locked_by/locked_at` + optimistic `version`; notes require body ≤2000.
- **Outputs / Response:** Lazy Livewire drawer: status flow, owner+shift, take-over (409 on stale), internal notes thread with replies, audit `story_events`.
- **Authorization:** `RbacService.assertCan(user, stories, publish/edit)`; owner/role check on take-over.

---

## 2. Logic (How)
1. Create `Livewire/Admin/WorkflowDrawer.php` (lazy) + `<x-drawer>` + `<x-workflow-strip>` + `<x-note-thread>`.
2. Service: `StoryService::transition`, `StoryService::takeOver` (force-release lock + chain-of-custody `story_events`), `NoteService::add`.
3. Handle 409 conflict with merge prompt; toast on success; invalidate list cache.
4. Internal notes `is_internal=true` never exposed to feed/search.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/WorkflowDrawer.php`
  - `resources/views/livewire/admin/workflow-drawer.blade.php`
  - `app/Services/StoryService.php`
- **Reference Files:** `app-data/english-news.html` drawer comments

---

## 4. Prompt (For the Coding AI)
> Build workflow drawer: status pill flow, owner+shift, take-over with optimistic lock (409), notes thread. Use StoryService + RbacService asserts. Lazy-loaded, no N+1, test take-over conflict.

---

## 5. Test Criteria
- [x] Transition happy path creates `story_versions` + `story_events`
- [x] Stale version → 409 merge prompt
- [x] Notes add requires auth + validates body
- [x] Internal notes not on feed/search

---

## 6. Completion Notes
- **Shipped:** 1:1 conversion of Workflow Drawer matching `app-data/english-news.html`:
  - 445px right slide-over drawer (`.wfd` + `.wfd-overlay`) with backdrop click-to-close, close button (✕), and Escape key handling.
  - Interactive workflow flow stepper (`Draft → In review → Needs work / Approved → Published`) with status step badges (`.wfd-step.done`, `.now`, `.now.bad`).
  - Owner panel with avatar initials (`.wf-ava`), staff name, role, and shift hours schedule.
  - Handover "Take over" button with optimistic version verification, raising and presenting 409 conflict notifications if concurrent edits occur.
  - Complete internal notes thread supporting `.nt-item.editor` (navy left border), `.nt-item.sub` (green left border), and `.nt-item.sys` (dashed border for shift handover/status changes).
  - Note reply composer (`textarea` + "Send" button) backed by dedicated `NoteService` validating character bounds (1–2000) and ensuring `is_internal = true`.
- **Tests:** `tests/Feature/NewsListTest.php` (tests drawer open, note addition, take over audit, 409 handling), `tests/e2e/news-list-faithful.spec.ts` (E2E open, stepper, owner, reply, thread update, close).
- **Live Smoke:** Drawer slides open, note reply added live in Dhaka timezone, close on ✕ confirmed.
- **Review:** All notes scoped internal; no N+1 on drawer relationships.

---

## 7. Prompt Ready?
- [x] Yes
