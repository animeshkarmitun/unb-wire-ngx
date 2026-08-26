# Pull Request Runbook — UNB Wire

> Guidelines for creating, reviewing, and merging Pull Requests for UNB Wire.

---

## 1. Opening a Pull Request

When all quality gates pass and live smoke testing is documented:

1. Push the branch to remote:
   ```bash
   git push -u origin HEAD
   ```
2. Create PR using the GitHub CLI (`gh pr create`) or web interface:
   ```bash
   gh pr create --template .github/PULL_REQUEST_TEMPLATE.md
   ```

---

## 2. PR Standards

- **Title:** Conventional Commit style including Task ID (e.g. `feat(editorial): add concurrent lock expiration job [EDIT-003]`).
- **Body:** Fill out all sections of `.github/PULL_REQUEST_TEMPLATE.md`.
- **Test Plan:** Explicitly list tests executed and routes smoked.
- **Review Report:** Include link or summary of `docs/workflow/reviews/<TASK-ID>-review.md`.

---

## 3. Pre-Merge Checklist

- [ ] GitHub Actions CI workflow (`ci.yml`) is completely green.
- [ ] No merge conflicts with `main`.
- [ ] `unb-wire-reviewer` approved without blockers.
- [ ] All related docs synced in the PR.
