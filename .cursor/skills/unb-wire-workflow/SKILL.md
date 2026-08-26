---
name: unb-wire-workflow
description: >-
  UNB Wire Laravel development pipeline — ACT-DP task files, trivial vs
  non-trivial decision tree, plan-then-verify steps, quality gates, live smoke,
  independent review via unb-wire-reviewer subagent, and commit/push/PR
  handoff. Use for non-trivial features, bugfixes, Filament/API/schema work,
  task decomposition, test plans, reviews, or when the user mentions workflow,
  ACT-DP, live smoke, or NEXT-TASK.
---

# UNB Wire — Workflow Skill

Orchestration layer for the UNB Wire development lifecycle.

## Companion Documents

| Doc | When |
|-----|------|
| `AGENTS.md` §0 / §10 | Always — size gate & quality gates |
| `docs/workflow.md` | Full multi-role pipeline |
| `docs/task-decomposition-protocol.md` | ACT-DP task format |
| `docs/workflow/live-test-runbook.md` | Pre-commit live smoke |
| `docs/workflow/git-strategy.md` | Branch / PR / merge rules |
| `docs/tasks/README.md` / `docs/workflow/NEXT-TASK.md` | Task index / pointer |
| `references/checklist.md` (this skill) | ACT-DP & Completion Notes checklist |

---

## Trivial vs Non-Trivial Decision Tree

| Size | Criteria | Process |
|------|----------|---------|
| **Trivial** | ≤1 file, few lines; no editorial/status/auth/schema change | Surgical edit + local quality gates |
| **Non-trivial** | Multi-file, domain logic, editorial/wire/subscription/API/schema | Full pipeline below |

---

## Non-Trivial Pipeline

```
Intake → (Architect?) → Planner (task file) → Coder → Self-test + live smoke
      → unb-wire-reviewer subagent → QA gates → commit → push → PR
```

1. **Task File:** Create in `docs/tasks/` using ACT-DP format.
2. **State Plan:**
   ```
   1. [Step] → verify: [check]
   2. [Step] → verify: [check]
   3. [Step] → verify: [check]
   ```
3. **Implement:** Touch only in-scope files. Match conventions.
4. **Verify:** `php -l` on changed PHP files; `php artisan test` on affected suites.
5. **Live Smoke:** Clear caches and verify live routes (`docs/workflow/live-test-runbook.md`). Record in Completion Notes.
6. **Independent Review:** Delegate to **`unb-wire-reviewer`** subagent in isolated context.
7. **Git Handoff:** Commit → Push → PR. Merge only when instructed.
