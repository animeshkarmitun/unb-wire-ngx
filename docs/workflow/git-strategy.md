# Git Strategy — UNB Wire

> **One-line rule:** `main` = production truth. Feature branch = same-day PR. Merge when PR CI is green.

---

## 1. Branch Naming Conventions

| Branch Pattern | Purpose | Max Lifetime |
|----------------|---------|--------------|
| `main` | Production-ready source of truth | Permanent |
| `feature/<scope>-<name>` | New capabilities or tasks | **≤ 3 days** |
| `fix/<scope>-<name>` | Bug fixes | **≤ 2 days** |
| `hotfix/<scope>-<name>` | Critical production fixes | **≤ 24 hours** |

---

## 2. Standard Branch Lifecycle

```
main → branch → implement → test & live-smoke → commit → push → PR → CI green → merge → delete branch
```

### Git Handoff Protocol for Agents
- Complete task implementation, quality gates, and live smoke.
- Commit with ACT-DP Task ID in message.
- Push branch and open PR immediately (without asking).
- Merge to `main` only upon explicit user instruction.

---

## 3. Standard Git Commands

```bash
# 1. Start from fresh main
git checkout main && git pull origin main
git checkout -b feature/wire-breaking-alerts

# 2. Stage and commit after verification
git add <target-files>
git commit -m "feat(wire): add breaking alert broadcast event [WIRE-002]"

# 3. Push and open PR
git push -u origin HEAD
gh pr create --title "feat(wire): add breaking alert broadcast event [WIRE-002]" --body "..."
```
