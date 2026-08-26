# Refactor Guidelines — UNB Wire

> Rules for refactors and cleanups. A refactor changes **structure, not behavior**.
> If behavior changes, it is not a refactor — split it into a separate `feat` or `fix` task.

---

## 1. Classification

| Scope | Process |
|-------|---------|
| **Trivial** (1 file, dead import removal) | Surgical edit + local quality gates |
| **Scoped** ("Extract WireFeedTransformer", "Clean up ArticleObserver") | Task file (`REFACTOR-NNN`) → normal workflow pipeline |
| **Vague** ("Clean up codebase", "Refactor everything") | **Do not start.** Clarify target files, motivation, and done criteria with human |

---

## 2. Safe Refactoring Patterns

- Extract reusable helper or service class used across **≥2 places**.
- Simplify oversized controller methods by delegating to dedicated Action or Service classes.
- Standardize variable/method naming across models and resources.
- Eliminate duplicated Eloquent query scopes.
- Remove confirmed dead code and unused imports created by recent changes.

---

## 3. Forbidden in a Pure Refactor

- Altering API response JSON shapes or breaking wire feed payloads.
- Modifying database schemas, column names, or migrations.
- Altering editorial status transitions, authorization policies, or lock mechanisms.
- Changing cache keys or queue payload contracts without migration path.
- Applying widespread aesthetic reformatting across untouched files.

---

## 4. Verification Checklist

- [ ] Full test suite passes (`php artisan test`).
- [ ] `php -l` clean on all touched files.
- [ ] Live smoke verification on touched endpoints (`docs/workflow/live-test-runbook.md`).
- [ ] No performance regression or N+1 queries introduced.
