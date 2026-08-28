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
| `M2-DB-002` | Clients: clients, client_users, client_api_keys | M2-DB-001 | ✅ | — | 2026-08-27 |
| `M2-DB-003` | Taxonomy: categories, tags | M2-DB-002 | ✅ | — | 2026-08-27 |
| `M2-DB-004` | Stories core: stories, versions, notes, events, story_tag | M2-DB-003 | ✅ | — | 2026-08-27 |
| `M2-DB-005` | Media core: media_batches, media_assets, media_reviews, media_tag, story_media | M2-DB-004 | ✅ | — | 2026-08-27 |
| `M2-DB-006` | Field ops: upload_sessions (assignments deferred) | M2-DB-005 | ✅ | — | 2026-08-27 |
| `M2-DB-007` | Distribution: packages, package_media, client_packages, client_channels, deliveries | M2-DB-006 | ✅ | — | 2026-08-27 |
| `M2-DB-008` | Search & archive: index_outbox, Meilisearch wiring | M2-DB-007 | ✅ | — | 2026-08-27 |
| `M3-FOUND` | Admin chrome conversion + base editorial UI | M2-DB-008 | ✅ | — | 2026-08-27 |
| `M4-SEARCH-001` | Search tenant tokens + entitlement resolver + Meili indexing | M3-FOUND | ✅ | — | 2026-08-27 |
| `M4-MEDIA-001` | Media library, presigned URLs, TUS uploads, story_media | M3-FOUND | ✅ | — | 2026-08-27 |
| `M4-API-001` | Client API keys, feed, portal APIs, rate limiting | M3-FOUND | ✅ | — | 2026-08-27 |
| `M4-BILL-001` | Billing, packages, subscriptions, entitlements | M3-FOUND | ✅ | — | 2026-08-27 |
| `M4-TEST-001` | Story workflow, RBAC, billing, seed workflow tests | M4-* | ✅ | — | 2026-08-27 |
| `M5-PORTAL-001` | Client portal (Next.js) + wire feed | M4-* | ✅ | — | 2026-08-27 |
| `M5-AI-001` | AI desk, budgets, guardrails, auto-publish | M4-* | ✅ | — | 2026-08-27 |
| `M5-OPS-001` | Reverb, Horizon, scheduler (embargo, archive, janitor) | M4-* | ✅ | — | 2026-08-27 |
| `M6-PERF-001` | Perf: cache, N+1, indexes, pagination | M5-* | ✅ | — | 2026-08-27 |
| `M6-SEC-001` | Security: RBAC, purify, hashed keys, rate limit | M5-* | ✅ | — | 2026-08-27 |
| `M6-AI-001` | AI publish gate, desk toggles | M5-* | ✅ | — | 2026-08-27 |
| `M6-OPS-001` | Horizon snapshot, outbox lag monitor | M5-* | ✅ | — | 2026-08-27 |
| `M6-DOCS-001` | Knowledge-inventory sync | M5-* | ✅ | — | 2026-08-27 |
| `M6-PORTAL-001` | Portal hardening + Meili token issuance | M5-* | ✅ | — | 2026-08-27 |
| `M7-E2E-001` | E2E Playwright suite (web/auth/rbac/portal/api/media) | M6-* | ✅ | — | 2026-08-27 |
| `M7-OBS-001` | Outbox lag monitor + horizon snapshot | M6-* | ✅ | — | 2026-08-27 |
| `M7-DEPLOY-001` | Live smoke runbook + launch checklist | M6-* | ✅ | — | 2026-08-27 |
| `QA-001` | QA audit remediations (XSS sanitizer, FormRequests, throttle, cache, embargo TZ) | M7-* | ✅ | — | 2026-08-28 |

---

## 5. How to Add a Task

1. Study the provided UI design / wireframe and derive functional requirements.
2. Decompose into an atomic task file in `docs/tasks/<TASK-ID>-<slug>.md` using `docs/task-decomposition-protocol.md`.
3. Add a new row to the table above.
