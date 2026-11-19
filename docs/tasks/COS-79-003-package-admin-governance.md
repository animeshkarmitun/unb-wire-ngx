# Task: COS-79-003 — System Admin package/override administration

**Status:** Pending
**Dependencies:** COS-79-001, COS-79-002
**Parent ADR:** proposed DEC-020

## 1. Contract (What)
- Package quota/add-on mutations are System Admin-only; other staff are view-only.
- A package with any `client_packages` reference cannot be hard-deleted; archive is allowed.
- System Admin can set/clear per-client quota overrides; every mutation is audit-logged.

## 2. Logic (How)
1. Add numeric quota + asset/add-on controls to the package UI.
2. Enforce `isSuperAdmin()` server-side on every mutation action.
3. Add override UI with inherit / numeric / unlimited states.
4. Block deletion when subscription history exists; show actionable message.

## 3. Context (Where)
- `PackagesManager`, package Blade, `ClientsManager`, client drawer, related tests.

## 4. Test Criteria
- [ ] Non-superadmin mutation returns 403; superadmin mutation persists.
- [ ] Delete blocked with subscription history; no-history delete works; archive works.
- [ ] Override set/clear and explicit unlimited are auditable.
