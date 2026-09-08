# Task: M10-HIST-006 — Story view: Timeline + Versions sections

**Status:** ⏳ Pending
**Dependencies:** M10-HIST-001, M10-HIST-004
**Parent ADR:** FR-NWS-019 (story detail: version history + audit history), `docs/plans/history-audit-design.md` §4

---

## 1. Contract (What)
- **Inputs / Validation:** `StoryView` component mounts story with `events.actor` + `versions.creator` already eager-loaded (`findWithAllRelations`) — **zero extra queries**.
- **Outputs / Response:** Two new sections in `story-view.blade.php`, styled to `app-data/story.html` chrome + design tokens:
  - **Timeline:** newest-first event list — actor avatar/initials, name, action label (from `StoryEvent::ACTIONS` map), `from → to` status chips, payload context (gate, handover users, kill reason), relative + absolute timestamp (Dhaka tz).
  - **Versions:** list of `v#`, creator name, timestamp.
- **Authorization:** Both sections gated `RbacService.can($user, 'history', 'view')` — server-side skip render + Livewire guard; Business Team / Client roles see nothing.

---

## 2. Logic (How)
1. Action→label map shared helper (Blade `$labels` from `StoryEvent::ACTIONS`) — no per-event queries.
2. Section visibility via a single `canViewHistory` computed prop on `StoryView`.
3. Payload context rendered as plain text chips — never raw JSON dump.
4. No JS diff/rendering in this task — pure server-rendered Blade (wire:ignore rules N/A).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Livewire/Admin/StoryView.php` (+ `canViewHistory` prop)
  - `resources/views/livewire/admin/story-view.blade.php`
  - `tests/Feature/StoryViewTest.php` (extend), `tests/e2e/story-view-history.spec.ts` (new)
- **Reference Files:** `app-data/story.html`, FR-NWS-019, M8-UI-002 components (`workflow-strip`, `note-thread` styling)

---

## 4. Prompt (For the Coding AI)
> Add Timeline + Versions sections to story-view.blade.php using already-eager-loaded events.actor and versions.creator (no new queries). Action labels from StoryEvent::ACTIONS map; payload context (gate/handover/kill reason) as text chips; Dhaka-tz relative+absolute timestamps. Gate both sections on can(user,'history','view') — Business Team/Client render none. Match story.html chrome/tokens. Tests: sections render with actor names for Admin; hidden for Business Team; no N+1 (query-count assertion).

---

## 5. Test Criteria
- [ ] Feature: Timeline renders events with actor names + action labels + payload context
- [ ] Feature: Business Team (history=0) gets no sections (server-side guard, not CSS hide)
- [ ] Query-count assertion: no additional queries vs pre-task baseline
- [ ] E2E: open story → timeline visible, version list visible
- [ ] `npm run build` clean (Blade-only change but gate stays green)

---

## 6. Completion Notes
- **Shipped:** —
- **Tests:** —
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
