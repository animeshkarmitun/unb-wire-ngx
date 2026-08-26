# Review Report: <TASK-ID>

**Task:** [Task title from docs/tasks/<TASK-ID>-*.md]
**Reviewer:** [unb-wire-reviewer / AI Subagent]
**Date:** YYYY-MM-DD
**Review Type:** Independent Review
**Status:** [✅ Approved / 🔧 Needs Fix / ⚠️ Approved with Warnings]
**Commit:** [git commit hash]

---

## 1. Executive Summary

[2-3 sentences summarizing the changes and overall quality verdict.]

---

## 2. Issues & Findings

| # | Severity | File | Line | Issue Description | Suggested Fix |
|---|----------|------|------|-------------------|---------------|
| 1 | 🔴 Blocker / 🟡 Warning / 🟢 Nit | `app/...` | 42 | [Description] | [Proposed solution] |

*(If no issues found, state: "No issues found. All quality gates and invariants satisfied.")*

---

## 3. Checklist Verification

### Editorial Domain & Business Rules
- [ ] Editorial lifecycle & status transitions preserved
- [ ] Concurrency locking respected
- [ ] Revision snapshots recorded
- [ ] Subscriber access tier capabilities enforced

### Code Quality & Architecture
- [ ] Eloquent models & Form Requests properly utilized
- [ ] Authorization policies applied
- [ ] Eager loading verified (No N+1)
- [ ] Database transactions used where appropriate

### Security & Sanitization
- [ ] Uploads validated by MIME / size
- [ ] Rich text purified
- [ ] Rate limits and Sanctum token abilities checked

### Verification & Testing
- [ ] Unit / Feature tests pass
- [ ] `php -l` syntax check clean
- [ ] Live smoke testing verified in Completion Notes

---

## 4. Final Verdict

**[✅ Approved for Merge / 🔧 Needs Fix]**
