# Lessons Learned & Agent Rules

## Git & Workflow Automation

### Automatic PR Merging When All Quality Gates Are Green
- **Lesson**: Do not ask the user for permission to merge a pull request after quality gates pass. When tests, linting, builds, and CI gates are green, automatically merge the pull request into `main` and pull the latest changes before reporting completion or proceeding to the next task.
- **Rule**: Once a PR is created and verified green, immediately execute `gh pr merge <PR_NUMBER> --merge`, checkout `main`, pull `origin/main`, and proceed. Do not prompt the user with "let me know if you would like me to merge PR #...".
