# Task: M10-HIST-004 — `story_events` payload enrichment + spec action vocabulary

**Status:** ⏳ Pending
**Dependencies:** M10-HIST-002 (same-file sequencing in `StoryService`)
**Parent ADR:** `app-data/v1-database-design.md` §5 `story_events`, FR-NWS-016 (kill reason audited)

---

## 1. Contract (What)
- **Inputs / Validation:** All event writes use the spec vocabulary: `created, sent_to_review, changes_requested, approved, published, auto_published, killed, archived, handover, ai_applied, note_added, restored`.
- **Outputs / Response:** `payload` jsonb populated: handover `{from_user, to_user}`, published/auto_published `{gate: 'manual'|'auto'}`, killed `{reason}`, ai_applied `{fields: [...]}`, note_added `{note_id}`. `from_status`/`to_status` unchanged.
- **Authorization:** N/A (internal; callers authorize).

---

## 2. Logic (How)
1. `StoryService::transition()`: action name = verb (not target status); detect auto-publish path (AI auto-publish / scheduler) → `auto_published` with `{gate:'auto'}`; manual publish → `published` `{gate:'manual'}`.
2. `StoryService::takeOver()` → action `handover` payload `{from_user, to_user}` (ids + names).
3. Kill path captures reason: `unpublish/kill` flow must accept a reason string (add modal input if the prototype lacks one) → `killed` `{reason}` per FR-NWS-016.
4. `AddNews::applyAi()` → `ai_applied` `{fields: [...]}` (which fields applied).
5. `NoteService::add()` → `note_added` `{note_id}`.
6. Centralize the vocabulary as `StoryEvent::ACTIONS` const map (single source of truth for UI labels in M10-HIST-006).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Models/StoryEvent.php` (ACTIONS const)
  - `app/Services/StoryService.php`, `app/Services/NoteService.php`
  - `app/Livewire/Admin/AddNews.php` (applyAi + kill reason input)
  - `resources/views/livewire/admin/add-news.blade.php` (kill reason modal, if missing)
  - `tests/Feature/StoryWorkflowTest.php` (extend)
- **Reference Files:** `docs/plans/history-audit-design.md` §3 item 2–3

---

## 4. Prompt (For the Coding AI)
> Align story_events action names to spec vocabulary via StoryEvent::ACTIONS const. transition() records verb + payload {gate:'manual'|'auto'} (auto-publish path → auto_published). takeOver → handover {from_user, to_user}. Kill flow accepts + records reason (FR-NWS-016; add modal input if UI lacks it). applyAi → ai_applied {fields}. NoteService::add → note_added {note_id}. Extend tests: each action name + payload asserted; kill without reason rejected.

---

## 5. Test Criteria
- [ ] Transition writes verb action names (`sent_to_review`, `published`, …), not raw status
- [ ] Auto-publish path → `auto_published` + `{gate:'auto'}`; manual → `published` + `{gate:'manual'}`
- [ ] Kill requires reason; event carries it (FR-NWS-016)
- [ ] Handover event carries `{from_user, to_user}`; AI apply carries `{fields}`
- [ ] Existing workflow tests updated & green; `php -l` clean

---

## 6. Completion Notes
- **Shipped:** —
- **Tests:** —
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
