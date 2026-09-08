# Task: M10-HIST-002 — Full-field version snapshots + snapshot-on-transition

**Status:** ✅ Completed
**Dependencies:** None (parallel-safe with M10-HIST-001)
**Parent ADR:** `docs/plans/history-audit-design.md` §3 + NFR §15 (`RevisionService`), AGENTS.md §2.1

---

## 1. Contract (What)
- **Inputs / Validation:** Every save/publish path writes a full-field immutable snapshot: `headline, sub_head, brief, body_html, category_id, dateline_city, dateline_at, priority, is_breaking, language, embargo_until, tags`.
- **Outputs / Response:** `RevisionService::snapshot(Story $story, User $user): StoryVersion` — writes `story_versions` with `version = stories.version` at save time, inside the caller's `DB::transaction()`.
- **Authorization:** N/A (internal service, callers already authorize).

---

## 2. Logic (How)
1. Create `app/Services/RevisionService.php` with `snapshot()`; persist via existing `StoryRepository::createVersion()`.
2. Rewire `StoryService::createDraft()` + `updateDraft()` to call `RevisionService::snapshot()` (replacing inline partial arrays).
3. **Version bump rule (UNIQUE-safe):** `StoryService::transition()` bumps `stories.version` +1 and snapshots at the new number — publish/kill/archive all leave a snapshot; concurrent stale edits → 409 (desired).
4. `takeOver()` does NOT snapshot (no content change) — event only.
5. Tags in snapshot: resolve to tag name list at snapshot time (snapshot must be self-contained).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Services/RevisionService.php` (new — `snapshot()` only; diff/restore come in M10-HIST-003)
  - `app/Services/StoryService.php`
  - `tests/Feature/StoryWorkflowTest.php` (extend)
- **Reference Files:** `app/Repositories/StoryRepository.php:276` (`createVersion`), `app-data/v1-database-design.md` §5 `story_versions`

---

## 4. Prompt (For the Coding AI)
> Create RevisionService::snapshot(Story, User) writing the full 12-field snapshot (tags as name list) via StoryRepository::createVersion, called inside existing transactions. Rewire createDraft/updateDraft to it. In transition(), increment stories.version then snapshot (UNIQUE (story_id, version) safety). takeOver: no snapshot. Extend StoryWorkflowTest: publish leaves version row with full snapshot fields; save-then-publish sequence never violates UNIQUE; version bump makes stale edit 409.

---

## 5. Test Criteria
- [ ] Publish/kill transitions each create a `story_versions` row (previously zero)
- [ ] Snapshot contains all 12 fields + tags list (assert JSON keys)
- [ ] `draft → save → save → publish` sequence: no UNIQUE violation, versions 1,2,3
- [ ] Existing stale-version 409 test still green after bump-on-transition
- [ ] `php -l` clean; no N+1 introduced (snapshot is a single insert)

---

## 6. Completion Notes
- **Shipped:** `RevisionService::snapshot()` created (12 fields + tags name list) via `StoryRepository::createVersion`; `StoryService::createDraft/updateDraft` now call `RevisionService::snapshot` (replacing inline partial arrays); `transition()` bumps `stories.version +1` and snapshots at new version (UNIQUE-safe); `StoryRepository::createVersion` return type fixed `void → StoryVersion`.
- **Tests:** `StoryWorkflowTest` 16/16 (4 new: transition snapshot fields, publish snapshot fields, version bump stale 409, save-then-publish no UNIQUE violation). Full suite: 345 passed. `schema-parity-check.php` green.
- **Live Smoke:** `php artisan test` + `php scripts/schema-parity-check.php` clean on sqlite :memory: (CI env). No pgsql schema drift (no migration changes).
- **Review:** — (milestone-end review in M10-HIST-010)

---

## 7. Prompt Ready?
- [x] Yes
