# Coding Runbook — Automated Task Runner

> Guidelines for coding agents executing tasks from `docs/workflow/NEXT-TASK.md`.

---

## 1. Execution Loop

```
Read NEXT-TASK.md → Read Task File → State 3-Step Plan → Implement → Verify Quality Gates → Live Smoke → Commit → Launch Independent Reviewer → Advance NEXT-TASK.md
```

---

## 2. Step-by-Step Runner Protocol

### Step 2.1: Initialization
1. Read `docs/workflow/NEXT-TASK.md`. If status is `STOP`, halt immediately.
2. Open target task file in `docs/tasks/`.
3. Verify all preceding dependencies are marked `✅ Completed`.

### Step 2.2: Implementation
1. Read Prompt and referenced Context files.
2. State implementation plan:
   ```
   1. [Step] → verify: [check]
   2. [Step] → verify: [check]
   3. [Step] → verify: [check]
   ```
3. Edit only in-scope files. Match existing codebase conventions.

### Step 2.3: Verification Gates
1. Run syntax check: `php -l` on all modified PHP files.
2. Run automated tests: `php artisan test --filter=...`.
3. If database schema was touched, verify: `php artisan migrate:fresh --seed`.
4. Perform live smoke test per `docs/workflow/live-test-runbook.md`.

### Step 2.4: Commit
1. Update task file: mark status `✅ Completed`, check off Test Criteria, and write Completion Notes (including live smoke record).
2. Commit with Conventional Commit syntax:
   ```bash
   git add <scoped-files>
   git commit -m "feat(scope): brief description [TASK-ID]"
   ```

### Step 2.5: Independent Review
1. Launch the isolated subagent **`unb-wire-reviewer`**.
2. If Reviewer flags 🔴 Blockers, mark task `🔧 Needs Fix` and resolve before proceeding.
3. If approved, advance `docs/workflow/NEXT-TASK.md` to the next pending task.

---

## 3. Stop Conditions

Halt execution and alert the user immediately if:
- A task dependency is incomplete or blocked.
- Tests fail and cannot be fixed within task scope.
- Reviewer reports unresolved 🔴 Blockers.
- Unanticipated breaking schema changes are required.
