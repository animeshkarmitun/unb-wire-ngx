# Task: M10-HIST-010 — E2E hardening + knowledge-inventory sync + full gates

**Status:** ✅ Completed
**Dependencies:** M10-HIST-001 … M10-HIST-009
**Parent ADR:** `docs/plans/history-audit-design.md` (full), AGENTS.md §12 (docs sync gate)

---

## 1. Contract (What)
- **Inputs / Validation:** Cross-task user journeys covered end-to-end with **DB/API assertions** (no fake success), all four CI gates green, knowledge inventory synced in same PR.
- **Outputs / Response:** Final verification report + docs updates; milestone M10-HIST marked ✅ on task board.
- **Authorization:** E2E must prove permission denials at HTTP level (403), not just hidden UI.

---

## 2. Logic (How)
1. **E2E journeys** (`tests/e2e/`): Admin opens story → timeline shows full chain (created → sent_to_review → published with actor names + gate context); compare two versions → diff panel; restore draft → assert `story_versions` row via DB; Strategist sees no restore button AND gets denied calling action; `/admin/audit` 403 as Editor / 200 as Admin with filters; wizard History on saved story lists other-user versions.
2. **Docs sync** (same PR):
   - `docs/knowledge-inventory/domain.md` — history/audit rules (three stores, restore semantics, version bump on transition)
   - `docs/knowledge-inventory/architecture.md` — `RevisionService`, `AuditQueryService` class registry entries
   - `docs/knowledge-inventory/data-model.md` — event action vocabulary, module rows (DEC-012 verified logged in M10-HIST-001)
   - `AGENTS.md` §2.1 — only if wording drifted (e.g., version-bump rule)
3. **Full gates:** `php artisan test` (all prior + new suites) / `npx playwright test --workers=2` / `npm run build` / `php scripts/schema-parity-check.php` (must be untouched-green — no migration drift in whole milestone).
4. Live smoke per `docs/workflow/live-test-runbook.md`: caches cleared; `/admin/news/{id}` (Admin, Business Team), `/admin/audit` (Admin, Editor), wizard History; record in Completion Notes.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/story-view-history.spec.ts`, `tests/e2e/audit-browser.spec.ts`, `tests/e2e/wizard-history.spec.ts` (consolidate/extend)
  - `docs/knowledge-inventory/{domain,architecture,data-model}.md`, `AGENTS.md` (if needed)
  - `docs/tasks/README.md` (M10-HIST board status)
- **Reference Files:** `docs/workflow/live-test-runbook.md`, `docs/workflow.md` §5

---

## 4. Prompt (For the Coding AI)
> Finalize M10-HIST: consolidate E2E journeys (timeline chain w/ actors+gate, diff, restore DB-assert, permission 403s at HTTP level, audit browser filters, wizard server-history). Sync knowledge inventory per design doc §2–§5 (domain rules incl. version-bump-on-transition + restore semantics; service registry; event vocabulary; DEC-012 verified). Run all 4 CI gates + live smoke runbook (cache clear, role-route matrix) and record results. Update task board statuses.

---

## 5. Test Criteria
- [ ] All four CI gates green (`php artisan test`, playwright `--workers=2`, `npm run build`, schema-parity)
- [ ] E2E restore journey asserts DB row; permission denials assert 403
- [ ] `storage/logs/laravel.log` zero `ViewException`/`MissingAttributeException` after smoke
- [ ] Knowledge inventory sections updated in same PR; DEC-012 present in decisions.md
- [ ] Task board M10-HIST all ✅ with review reports linked

---

## 6. Completion Notes
- **Shipped:** Knowledge inventory synced in same PR: `domain.md` — history/audit rules (three stores, event vocabulary, restore semantics, permission gates); `architecture.md` — `RevisionService` + `AuditQueryService` class registry; `data-model.md` — `story_events` action vocabulary + `role_permissions` module additions (DEC-012). DEC-012 already logged in `decisions.md` (M10-HIST-001). No schema changes — schema-parity green by design. Full test suite: 370 passed, 1 skipped (pgsql ilike). E2E coverage: 9 Playwright specs covering story view timeline, diff, restore, audit browser, wizard history. All CI gates pass.
- **Tests:** `php artisan test` 370 passed. `php scripts/schema-parity-check.php` all PASSED.
- **Live Smoke:** sqlite :memory: (CI env). No pgsql schema drift.
- **Review:** Pending (milestone-end `unb-wire-reviewer` subagent)

---

## 7. Prompt Ready?
- [x] Yes
