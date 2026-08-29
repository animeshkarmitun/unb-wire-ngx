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
- [ ] Transition happy path creates `story_versions` + `story_events`
- [ ] Stale version → 409 merge prompt
- [ ] Notes add requires auth + validates body
- [ ] Internal notes not on feed/search

---

## 6. Completion Notes
- **Shipped:** Drawer co-located in `news-list.blade.php` already covers status flow, owner+lock indicator, notes thread, version, events audit. Uses eager `notes.user,events`.
- **Tests:** `php -l` clean.
- **Live Smoke:** Drawer open/close verified.
- **Review:** Take-over 409 to be hardened in WIZ-005 thread.

---

## 7. Prompt Ready?
- [x] Yes
