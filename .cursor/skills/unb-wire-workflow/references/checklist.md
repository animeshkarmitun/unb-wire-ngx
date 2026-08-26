# Workflow Checklists (On-Demand) — UNB Wire

Reference checklist for creating and completing ACT-DP task files.

---

## 1. ACT-DP Task File Sections

Every task file in `docs/tasks/` must include:
1. **Contract (What):** Validation inputs, response DTO / Blade view, error status codes, authorization gates.
2. **Logic (How):** Step-by-step algorithms, database transactions, cache invalidation, webhook jobs.
3. **Context (Where):** Exact files to create/modify, reference models/policies, test file paths.
4. **Prompt (For Coding AI):** Standalone copy-pasteable implementation prompt.
5. **Test Criteria:** Verifiable test cases to be checked off.
6. **Completion Notes:** Summary of shipped files, test results, live-smoke records.
7. **Prompt Ready?:** Marked `[x] Yes` when sections 1-5 are complete.

---

## 2. Completion Notes Template

```markdown
#### Completion Notes
- **Shipped:** [Summary of files created/modified and feature delivered]
- **Tests:** `php artisan test --filter=...` → passed (N tests, N assertions)
- **Live Smoke:** [Caches cleared; tested routes /admin/articles, GET /api/v1/wire/latest as desk-editor; observed expected output]
- **Review:** [docs/workflow/reviews/<TASK-ID>-review.md]
- **Follow-ups:** [none | TASK-ID]
```
