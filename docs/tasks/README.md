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

## 5. Faithful Prototype Conversion Milestone (`M8-FAITHFUL`) — 2026-08-29 Design: `docs/plans/faithful-prototype-conversion-design.md`

> Scaffold → faithful 1:1. Build order = README §11 (foundation → simple → lists → DAM → wizard). Each task ≤3k tokens, single responsibility.

| Task ID | Title | Dependencies | Status | Review | Completed |
|---------|-------|--------------|--------|--------|-----------|
| `M8-FOUND-001` | Tailwind tokens + fonts + gradients g1–g8 | M8-PLAN | ✅ | — | 2026-08-29 |
| `M8-FOUND-002` | Shared chrome audit (layout/sidebar/topnav/toast) | M8-FOUND-001 | ✅ | — | 2026-08-29 |
| `M8-UI-001` | Component library #1 (btn/card/pill/tabs/modal/drawer/switch/dropdown/thumb) | M8-FOUND-002 | ✅ | — | 2026-08-29 |
| `M8-UI-002` | Component library #2 (workflow-strip/note-thread/filter-bar/data-table) | M8-UI-001 | ✅ | — | 2026-08-29 |
| `M8-DASH-001` | Dashboard faithful (KPIs + stories + clients + FAB) | M8-FOUND-002 | ✅ | — | 2026-08-29 |
| `M8-NEWS-001` | News list faithful (en+bn) | M8-UI-002 | ✅ | — | 2026-08-29 |
| `M8-NEWS-002` | Workflow drawer (status flow + take-over + notes) | M8-NEWS-001 | ✅ | — | 2026-08-29 |
| `M8-PHOTO-001` | UNB Photo Manager faithful | M8-UI-002 | ✅ | — | 2026-09-05 |
| `M8-PHOTO-002` | Field intake approval queue | M8-PHOTO-001 | ✅ | — | 2026-09-05 |
| `M8-PHOTO-003` | AP Photo Manager faithful | M8-UI-002, M8-PHOTO-001 | ✅ | — | 2026-09-05 |
| `M8-CLIENT-001` | Clients Manager faithful (5-stat, bulk bar, 4-channel, drawer, modals, wizard) | M8-UI-002, M2-DB-002 | ✅ | — | 2026-09-05 |
| `M8-PACK-001` | Packages & Add-ons Manager faithful (4-stat, cards, add-on table, editor preview, archive) | M8-UI-002, M2-DB-007 | ✅ | — | 2026-09-05 |
| `M8-ROLE-001` | Roles & Access Manager faithful (4-stat, role cards, drawer with presets & matrix, people list, invite, delete, audit) | M8-UI-002, M2-DB-001 | ✅ | — | 2026-09-05 |
| `M8-AI-001` | AI Settings Manager faithful (banner, 3-desk toggles, auto-publish modal & allowlist, budget, style prompt, kill switch) | M8-UI-002, M2-DB-001, M8-ROLE-001 | ✅ | — | 2026-09-06 |
| `M8-DELIV-001` | Delivery Settings Manager faithful (5 cards: auto-push FTP/SFTP, API keys/webhooks, email alerts, download history, dispatch engine) | M8-UI-002, M2-DB-007 | ✅ | — | 2026-09-06 |
| `M8-PORTAL-001` | Client Portal faithful 1:1 conversion (Next.js, 3 wire views, search token, media library, lightbox) | DEC-006, M8-DELIV-001 | ✅ | — | 2026-09-06 |
| `M8-STORY-001` | Story Reader View faithful 1:1 conversion (story.html + FR-NWS-019 editorial chrome) | M8-PORTAL-001 | ✅ | — | 2026-09-06 |
| `M8-SERV-001` | Wire Service Frontpage faithful 1:1 conversion (english-service.html + bn parity) | M8-STORY-001 | ✅ | — | 2026-09-06 |
| `M8-SIMPLE-001` | Simple pages chrome-faithful (clients/packages/roles/ai-settings/service) | M8-UI-002 | ✅ | — | 2026-08-29 |
| `M8-WIZ-001` | Wizard shell (stepper + sticky nav + autosave) | M8-UI-002 | ✅ | — | 2026-08-29 |
| `M8-WIZ-002` | Body: Quill wire toolbar + find bar + fullscreen | M8-WIZ-001 | ✅ | — | 2026-08-29 |
| `M8-WIZ-003` | Media: featured 1200x630 + attach grid + drop + doc-import | M8-WIZ-001 | ✅ | — | 2026-08-29 |
| `M8-WIZ-004` | Tags/type/seg-control/distribution + live preview | M8-WIZ-001 | ✅ | — | 2026-08-29 |
| `M8-WIZ-005` | Desk workflow strip + internal notes | M8-WIZ-001 | ✅ | — | 2026-08-29 |
| `M8-WIZ-006` | AI desk faithful (drawer + diff + gate + kill-switch) | M8-WIZ-001 | ✅ | — | 2026-08-29 |
| `M8-E2E-001` | Wizard E2E (draft→publish + fan-out) | M8-WIZ-006 | ✅ | — | 2026-08-29 |
| `M8-QA-001` | A11y + Bangla/NFC + perf (N+1/cache) | M8-E2E-001 | ✅ | — | 2026-08-29 |

---

## 6. Remediation (`M8-FIX`) — 2026-08-29 `docs/plans/remediation-fix-all-v2.md`

> Hotfix P0 done (Upload wired). P1–P8 in progress one-by-one with interaction E2E + live smoke gates.

## 7. Schema-Code Parity (`M9-SCHEMA`) — 2026-08-29 `docs/schema-code-mismatch-report.md` → `DEC-011`

| Task ID | Title | Dependencies | Status | Review | Completed |
|---------|-------|--------------|--------|--------|-----------|
| `M9-SCHEMA-001` | Critical: FK restrict, grants, assignments | M8-QA-001 | ✅ | — | 2026-08-29 |
| `M9-SCHEMA-002` | Timestamptz sweep + nullability | M9-SCHEMA-001 | ✅ | — | 2026-08-29 |
| `M9-SCHEMA-003` | Indexes, casts, factories, encoding | M9-SCHEMA-002 | ✅ | — | 2026-08-29 |
| `M9-SCHEMA-004` | Docs ratification (invoices, is_internal, CHECKs, partitions) | M9-SCHEMA-003 | ✅ | — | 2026-08-29 |

---

## 8. History & Audit Trail (`M10-HIST`) — 2026-09-08 Design: `docs/plans/history-audit-design.md` (+DEC-012)

> Backend-first build order: data enrichment (001–005) → UI (006–009) → hardening (010).
> Closes FR-NWS-019 version history/audit history, FR-NWS-003 revision history, FR-NTF-003 audit trail, NFR §15 `RevisionService`/`AuditQueryService`. No new tables/columns — seed data + code paths only.

| Task ID | Title | Dependencies | Status | Review | Completed |
|---------|-------|--------------|--------|--------|-----------|
| `M10-HIST-001` | RBAC `history` + `audit` permission modules (seeder, matrix UI, DEC-012) | — | ⏳ | — | — |
| `M10-HIST-002` | Full-field version snapshots + snapshot-on-transition (`RevisionService::snapshot`) | — | ⏳ | — | — |
| `M10-HIST-003` | `RevisionService::diff` + `restore` (non-destructive, lock-safe) | M10-HIST-002 | ⏳ | — | — |
| `M10-HIST-004` | `story_events` payload enrichment + spec action vocabulary | M10-HIST-002 | ⏳ | — | — |
| `M10-HIST-005` | Sensitive actions → `audit_logs` (ip/ua/correlation/diff) + `AuditQueryService` | M10-HIST-001, M10-HIST-004 | ⏳ | — | — |
| `M10-HIST-006` | Story view: Timeline + Versions sections | M10-HIST-001, M10-HIST-004 | ⏳ | — | — |
| `M10-HIST-007` | Version diff viewer + restore action (story view) | M10-HIST-003, M10-HIST-006 | ⏳ | — | — |
| `M10-HIST-008` | Wizard History modal → server-side versions | M10-HIST-003 | ⏳ | — | — |
| `M10-HIST-009` | Global audit log browser (`/admin/audit`) | M10-HIST-005 | ⏳ | — | — |
| `M10-HIST-010` | E2E hardening + knowledge-inventory sync + full CI gates | M10-HIST-001…009 | ⏳ | — | — |

---

## 9. How to Add a Task

1. Study the provided UI design / wireframe and derive functional requirements.
2. Decompose into an atomic task file in `docs/tasks/<TASK-ID>-<slug>.md` using `docs/task-decomposition-protocol.md`.
3. Add a new row to the table above.
