# Lessons Learned & Agent Rules

## Git & Workflow Automation

### Automatic PR Merging When All Quality Gates Are Green
- **Lesson**: Do not ask the user for permission to merge a pull request after quality gates pass. When tests, linting, builds, and CI gates are green, automatically merge the pull request into `main` and pull the latest changes before reporting completion or proceeding to the next task.
- **Rule**: Once a PR is created and verified green, immediately execute `gh pr merge <PR_NUMBER> --merge`, checkout `main`, pull `origin/main`, and proceed. Do not prompt the user with "let me know if you would like me to merge PR #...".

## Quality Assurance & E2E Testing

### Exhaustive Interactive Control Verification in E2E
- **Lesson**: High-level workflow tests and happy paths are insufficient if individual secondary toggles, collapse/expand controls, and utility buttons from prototype conversions are left untested and unwired (e.g., `#aiStartToggle` Hide/Show button on `add-news`).
- **Rule**: Every interactive button, toggle, and disclosure trigger in prototype conversions must have:
  1. A verified working handler (via Alpine or component JS).
  2. An explicit E2E assertion verifying both open/closed or active/inactive states upon user click.
  3. Pre-flight verification audits checking all prototype interactive elements before declaring milestones complete.
