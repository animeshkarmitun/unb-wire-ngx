# Development Workflow — UNB Wire

> End-to-end multi-role development workflow for AI-assisted engineering on **UNB Wire**.
> Defines agent roles, handoff artifacts, quality gates, and git lifecycle from intake to merge.

---

## 1. Overview

This project uses a **structured multi-role workflow** inspired by the Architect/Editor and specialized subagent pattern. Structured markdown files serve as persistent handoff artifacts between roles.

```
Client / Product Request
        ↓
   [Architect]     →  Design Doc (docs/plans/ or architecture-decision.md)
        ↓
   [Planner]       →  Task Files (docs/tasks/ via ACT-DP)
        ↓
   [Coder]         →  Code + Tests + Self-Review
        ↓
   [QA Runner]     →  php -l, pest/phpunit, migrate:fresh
        ↓
   [Smoke Tester]  →  Live cache-clear + browser/API smoke test
        ↓
   [Reviewer]      →  Independent review via unb-wire-reviewer subagent
        ↓
   [Git Handoff]   →  Commit → Push → PR (automated) → Merge (human)
```

Each role produces a persistent, verifiable artifact.

---

## 2. Roles & Responsibilities

### Role 1: Architect
**Responsibility:** High-level system design, data architecture, and wire distribution contracts.
**Output:**
- `docs/plans/<feature>-design.md`
- `docs/knowledge-inventory/decisions.md` (ADR updates)
**When involved:** New modules, breaking schema changes, subscription tier logic, webhook architectures.

### Role 2: Planner
**Responsibility:** Decompose requirements into atomic, context-bounded tasks.
**Output:**
- `docs/tasks/<ID>-*.md` (ACT-DP format)
- `docs/tasks/README.md` (Task board update)
**Quality Gate:** Each task is atomic, under token budget, has verifiable Test Criteria, and includes a copy-pasteable Prompt.

### Role 3: Coder
**Responsibility:** Implement one task at a time.
**Output:**
- Application code & migrations
- Automated tests (Feature & Unit)
- Updated task Completion Notes
**Quality Gate:** `php -l` clean, tests pass, eager loading verified, strictly scoped changes.

### Role 4: QA Runner
**Responsibility:** Automated verification of syntax, tests, and database migrations.
**Output:** Test execution results and migration logs.
**Quality Gate:** `php artisan test` green, `migrate:fresh --seed` clean.

### Role 5: Smoke Tester
**Responsibility:** Live route and role verification on runtime changes.
**Output:** Smoke testing record in task Completion Notes.
**Quality Gate:** Compiled caches cleared, routes return 200, role permissions verified, no 500 errors in logs.

### Role 6: Reviewer
**Responsibility:** Independent code review against project standards, security, and domain invariants.
**Output:** `docs/workflow/reviews/<TASK-ID>-review.md`
**Quality Gate:** Delegated to **`unb-wire-reviewer`** subagent in isolated context. No 🔴 Blockers remaining.

---

## 3. Workflow Steps

### Step 1: Intake
Analyze request from client, issue tracker, or roadmap. Determine if Architect design is needed.

### Step 2: Design (Non-trivial features)
Architect creates `docs/plans/<feature>-design.md` or logs ADR in `docs/knowledge-inventory/decisions.md`.

### Step 3: Planning (ACT-DP)
Planner creates task files in `docs/tasks/` using `docs/task-decomposition-protocol.md`.

### Step 4: Coding
Coder implements the Prompt section. Before touching code, states the 3-step verification plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

### Step 5: Self-Test & Quality Gates
Coder verifies `php -l`, runs tests, clears caches, and conducts live smoke testing per `docs/workflow/live-test-runbook.md`.

### Step 6: Independent Review
Reviewer subagent (`unb-wire-reviewer`) reviews the diff against `AGENTS.md` and generates review report.

### Step 7: Git Handoff
Commit with task ID (`feat(wire): add rss feed generator [WIRE-001]`), push branch, open PR.

---

## 4. Git Strategy & Handoff

> **Core Rule:** `main` = production truth. Feature branch = same-day PR. Merge when PR CI is green.

| Action | Timing | Ask First? |
|--------|--------|------------|
| **Commit** | Gates pass; clean diff; no secrets | Only if mixing unrelated WIP or user said don't |
| **Push** | After commit on feature branch | Only if directly on `main` |
| **Open PR** | Same session as push | No (automatic) |
| **Merge** | CI green + human approval | Agent merges only when instructed |

---

## 5. Documentation Sync Gate

Docs must be updated **in the same commit as code**:
- Editorial/Wire rules → `docs/knowledge-inventory/domain.md`
- Architecture decisions → `docs/knowledge-inventory/decisions.md`
- API contracts → `docs/knowledge-inventory/architecture.md`
- Schema changes → `docs/knowledge-inventory/data-model.md`

---

## 6. Schema Parity Gate (prevents M9-SCHEMA drift)

Every PR that touches `database/migrations/*`, `app/Models/*`, `database/factories/*`, `app-data/v1-database-design.md` or `docs/knowledge-inventory/decisions.md` must pass:

1. `php scripts/schema-parity-check.php` — checks FK `RESTRICT` vs `SET NULL`, `timestamptz` bare-timestamp == 0 (business tables), CHECK enums, `assignments`/`invoices`/`is_internal` presence, indexes `DESC`, model `$casts` coverage. See `docs/workflow/schema-parity-runbook.md`.
2. `php artisan migrate:fresh --seed` on sqlite (CI) + pgsql (reviewer manual if pgsql available).
3. `DEC-NNN` required for any new table/column/CHECK invented outside `v1-database-design.md` — auto-fail if migration adds a table not in design without a DEC patch in same PR.

Coder must run `php scripts/schema-parity-check.php` locally before push; CI job `schema-parity` blocks merge on fail.
