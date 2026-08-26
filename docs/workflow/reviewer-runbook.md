# Reviewer Runbook — UNB Wire

> Rules, checklist, and severity guidelines for independent code review.

---

## 1. Review Principles

- **Independent Context:** Review is executed by the isolated **`unb-wire-reviewer`** subagent to eliminate coder confirmation bias.
- **Read-Only:** The Reviewer **never edits code**. It produces a structured review report and assigns a verdict.
- **Enforce Invariants:** Strict enforcement of domain rules, security, eager loading, and quality gates.

---

## 2. Review Checklist

### 2.1 Domain & Business Rules
- [ ] Editorial lifecycle transitions strictly validated.
- [ ] Editorial locks properly acquired and released.
- [ ] Immutable revision generated on publish/update.
- [ ] Subscriber access tier permissions enforced.

### 2.2 Laravel Standards
- [ ] Eloquent used; no raw SQL without justification.
- [ ] Form Request classes for validation.
- [ ] Filament resources used for admin CRUD.
- [ ] Policies enforce authorization gates.
- [ ] Migrations used for all schema changes.

### 2.3 Performance & Security
- [ ] Eager loading applied (no N+1 queries).
- [ ] Database transactions on multi-model mutations.
- [ ] File uploads validated by MIME and stored securely.
- [ ] Rich text purified against XSS vulnerabilities.
- [ ] API tokens hashed and rate-limited.

### 2.4 Testing & Live Smoke
- [ ] Automated tests cover new/modified logic.
- [ ] `php artisan test` green.
- [ ] Completion Notes contain live smoke record per `docs/workflow/live-test-runbook.md`.

---

## 3. Severity Levels

| Severity | Definition | Action Required |
|----------|------------|-----------------|
| **🔴 Blocker** | Security vulnerability, broken editorial flow, test failure, missing live-smoke record | Must fix before merge (`🔧 Needs Fix`) |
| **🟡 Warning** | Missing eager load, suboptimal naming, minor documentation gap | Should fix; acceptable to defer with rationale |
| **🟢 Nit** | Minor formatting preference, non-critical comments | Optional polish |

---

## 4. Output

Save reports in `docs/workflow/reviews/<TASK-ID>-review.md` using `docs/workflow/review-template.md`.
