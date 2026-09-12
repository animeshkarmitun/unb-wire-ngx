# Task: M10-HIST-003 — `RevisionService::diff` + `restore` (non-destructive, lock-safe)

**Status:** ✅ Completed
**Dependencies:** M10-HIST-002
**Parent ADR:** NFR §15 (`RevisionService: version snapshots, diff(v1,v2), restore`), `docs/plans/history-audit-design.md` §3

---

## 1. Contract (What)
- **Inputs / Validation:** `diff(StoryVersion $a, StoryVersion $b): array` — pure, no writes. `restore(Story $story, int $version, User $actor): Story` — allowed only when story status ∈ `draft|in_review|changes_requested`; caller passes optimistic `version` (409 on stale, reuses `StoryService` conflict semantics).
- **Outputs / Response:**
  - `diff`: `['fields' => [['field','from','to'], …], 'body' => [segments: [token, changed: bool]]]` — field rows for meta, word-level token diff for `body_html`.
  - `restore`: fresh `Story` — writes NEW `story_versions` row at `stories.version + 1` with restored content, bumps `stories.version`, records `story_events` action `restored` payload `{from_version, to_version}`. Never deletes/mutates existing versions.
- **Authorization:** `RbacService.assertCan($actor, 'stories', 'edit')` + soft-lock acquisition (reuse `StoryService` lock helpers).

---

## 2. Logic (How)
1. `diff()`: iterate snapshot keys; string-equal → skip; scalar fields → field row; `body_html` → whitespace-split token LCS diff (plain tokens, tags attached to preceding text token; keep simple).
2. `restore()` inside `DB::transaction()`: status guard → permission assert → lock/optimistic check → apply snapshot fields back to story (tags re-attach by name) → `snapshot()` at new version → `story_events` → return fresh story.
3. Restored `embargo_until` respects explicit-tz → UTC storage convention.
4. Audit-log write happens in M10-HIST-005 wiring (do not double-implement here).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `app/Services/RevisionService.php` (extend)
  - `tests/Unit/RevisionServiceTest.php` (new)
  - `tests/Feature/RevisionRestoreTest.php` (new)
- **Reference Files:** `app/Services/StoryService.php` (lock/409 semantics), `app-data/v1-database-design.md` §5

---

## 4. Prompt (For the Coding AI)
> Extend RevisionService with diff(a,b): field-level rows for differing meta keys + word-level LCS token diff for body_html (server-side, pure). restore(story, version, actor): guard status in draft/in_review/changes_requested, assertCan stories edit, optimistic version 409, apply snapshot fields (tags by name, embargo UTC), bump version, write NEW snapshot row + story_events 'restored' {from_version, to_version}, all in DB::transaction. Tests: diff detects field+body changes; restore adds version row (count+1) and never deletes; restore on published throws; stale version 409; event recorded with payload.

---

## 5. Test Criteria
- [ ] Unit: `diff` returns exact field rows + body segments for known inputs; identical versions → empty diff
- [ ] Feature: restore on `draft` creates new version, old versions intact (assert count & rows)
- [ ] Feature: restore on `published`/`archived`/`killed` rejected (422-style domain error)
- [ ] Feature: stale optimistic version → 409
- [ ] Feature: `story_events` gains `restored` with `{from_version, to_version}` payload
- [ ] `php -l` clean

---

## 6. Completion Notes
- **Shipped:** `RevisionService::diff(a,b)` — field-level rows for meta + word-level token diff for body_html (server-side, pure). `restore(story, version, actor, expectedVersion)` — status guard (draft/in_review/changes_requested), optimistic version 409, applies snapshot fields (tags re-attach by name, is_breaking/priority defaulted for NOT NULL safety), bumps version, writes NEW snapshot + story_events `restored` {from_version, to_version}. Snapshot `is_breaking` boolean-cast + `priority` defaulted to 'routine' to prevent NOT NULL violations. Circular dependency with StoryService avoided by inlining lock logic.
- **Tests:** `RevisionServiceDiffTest` 5/5 (unit: identical→empty, field diff, tags diff, body changes, body no-change). `RevisionRestoreTest` 7/7 (feature: new version row, old versions intact, published/killed→denied, stale 409, event payload, tag reattach). Full suite: 357 passed. Schema parity green.
- **Live Smoke:** sqlite :memory: (CI env). No pgsql schema drift.
- **Review:** — (milestone-end review in M10-HIST-010)

---

## 7. Prompt Ready?
- [x] Yes
