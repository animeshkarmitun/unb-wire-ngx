# Tasks Index & Status Board — UNB Wire

> Central registry of decomposed ACT-DP tasks for the project.
> **Note:** Tasks will be created directly based on the UI screens & functional requirements provided by the user.

---

## 1. Status Overview

| Status Icon | Meaning |
|-------------|---------|
| ⏳ | Pending / Backlog |
| 🔄 | In Progress |
| ✅ | Completed & Verified |
| 🔧 | Needs Fix (Blocker Found) |
| ⏸️ | Blocked / Waiting on Dependency |

---

## 2. Setup & Tooling Milestone (`M0-TOOL`)

| Task ID | Title | Dependencies | Status | Review | Completed |
|---------|-------|--------------|--------|--------|-----------|
| `M0-TOOL-001` | Workflow, runbooks, skills, hooks & reviewer subagents setup | — | ✅ | Approved | 2026-08-25 |
| `M0-TOOL-002` | Knowledge-inventory sync with app-data v1 contracts; field apps descoped (DEC-006/007/008) | — | ✅ | — | 2026-08-26 |

---

## 3. Foundation Milestone (`M1-BOOT`)

| Task ID | Title | Dependencies | Status | Review | Completed |
|---------|-------|--------------|--------|--------|-----------|
| `M1-BOOT-001` | Laravel modular monolith scaffold + base admin chrome | M0-TOOL-001/002 | ✅ | — | 2026-08-26 |

---

## 4. Product Tasks Board

*UI prototypes & requirements received in `app-data/` (2026-08-26). Decompose per `docs/task-decomposition-protocol.md` — build order: DB design §10 migration tasks, then prototype conversion order (app-data README §11).*
***Deferred:** field apps (MoJo / Photo desk PWA), FR-FLD-001…010, `Modules/Field` — see `DEC-008`.*

| Task ID | Title | Dependencies | Status | Review | Completed |
|---------|-------|--------------|--------|--------|-----------|
| `M2-DB-001` | Auth base: roles, permissions, users, devices, tokens | M1-BOOT-001 | ✅ | — | 2026-08-27 |
| — | *Next: M2-DB-002 clients (clients, client_users, client_api_keys) per design §10.2* | — | ⏳ | — | — |

---

## 5. How to Add a Task

1. Study the provided UI design / wireframe and derive functional requirements.
2. Decompose into an atomic task file in `docs/tasks/<TASK-ID>-<slug>.md` using `docs/task-decomposition-protocol.md`.
3. Add a new row to the table above.
