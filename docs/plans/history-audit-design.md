# History & Audit Trail — Feature Design (M10-HIST)

**Date:** 2026-09-08
**Status:** Approved for planning → `docs/tasks/M10-HIST-*.md`
**Canonical refs:** FR-NWS-019 (story detail: version history + audit history), FR-NWS-016 (kill audited with reason), FR-NWS-003 (revision history), FR-NTF-003 (audit trail), NFR §15 (`RevisionService`, `AuditQueryService`), `app-data/v1-database-design.md` §5 (`story_versions`, `story_events`, `audit_logs`), AGENTS.md §2.1
**Related:** DEC-007, DEC-011 (partitioning deferred), new **DEC-012** (RBAC history/audit modules)

---

## 1. Problem Statement & Requirements

Audit data foundations exist (3 tables + models + partial writes) but the feature is not delivered. Gap analysis (2026-09-08):

| Gap | Evidence |
|-----|----------|
| Story events captured but never rendered | `StoryRepository::findWithAllRelations()` eager-loads `events.actor`; `story-view.blade.php` has no timeline section |
| Version snapshots minimal & inconsistent | `createDraft` snapshots 2 fields, `updateDraft` 3, transitions snapshot nothing |
| No `RevisionService` | NFR §15 specifies `diff(v1,v2)` + `restore`; not implemented anywhere |
| Sensitive story actions never reach `audit_logs` | FR-NTF-003 list only partially wired (role/user mgmt + AI settings log; story transitions/publish/kill/notes/handovers/API keys do not) |
| `audit_logs` context columns never populated | `ip`, `user_agent`, `correlation_id`, `diff` exist in schema, always null |
| No history/audit RBAC | `role_permissions` module list has no `history`/`audit` — visibility uncontrolled |
| Wizard "History" is localStorage-only | `unb_rev_v1` snapshots lost on tab close; app-data README prototype→implementation table maps it to `story_versions` via `RevisionService` |

**Scope:** who edited what (versions), who did what (events timeline + audit ledger), who can see history (RBAC), diff/restore (RevisionService), admin audit browser.

**Explicitly deferred:** `audit_logs` partitioning + 7-year retention (DEC-011), login/logout audit (not in FR-NTF-003 action list), media action audit (not in spec list).

---

## 2. Architecture & Component Boundaries

**Build order: backend-first.** UI tasks consume enriched data (full snapshots, payload context, audit rows); building UI first forces rework.

Three stores, three responsibilities — no overlap:

| Store | Responsibility | Writer |
|-------|---------------|--------|
| `story_versions` | Immutable full-field content snapshots | `RevisionService::snapshot()` called by `StoryService` inside its transaction |
| `story_events` | Workflow timeline / chain of custody (story-scoped) | `StoryService`, `NoteService`, `AddNews::applyAi` |
| `audit_logs` | Cross-module append-only compliance ledger | `AuditLogRepository::log()` from all sensitive-action paths |

Story actions write **both** `story_events` (workflow context) and `audit_logs` (compliance). Role/user/API-key/AI actions write `audit_logs` only.

New domain classes (NFR §15):
- **`RevisionService`** (Stories module): `snapshot(Story, User): StoryVersion`, `diff(StoryVersion a, StoryVersion b): array`, `restore(Story, int version, User): Story`
- **`AuditQueryService`** (Notifications module per NFR table): `forEntity(string $type, int|string $id)`, `search(filters): LengthAwarePaginator`

Existing `StoryRepository::createVersion/createEvent` remain the persistence helpers; `RoleRepository`'s duplicate audit methods delegate to `AuditLogRepository` (no behavior change).

---

## 3. Data Model & Schema Impact

**No new tables or columns.** All three tables already match `v1-database-design.md` (M9-SCHEMA, DEC-011). Changes are code-path and seed-data only:

1. **`role_permissions` module rows `history` + `audit`** — design §`role_permissions` module list is open-ended ("…"), ratified as **DEC-012** (logged in M10-HIST-001's commit).
2. **`story_events.action` vocabulary** aligned to design §5: `created`, `sent_to_review`, `changes_requested`, `approved`, `published`, `auto_published`, `killed`, `archived`, `handover`, `ai_applied`, `note_added`, `restored`.
3. **`story_events.payload`** populated per design: handover `{from_user, to_user}`, published `{gate: 'manual'|'auto'}`, killed `{reason}` (FR-NWS-016), restored `{from_version, to_version}`, ai_applied `{fields: [...]}`.
4. **`story_versions.snapshot` = full field snapshot** per design ("headline/brief/body/meta"): `headline, sub_head, brief, body_html, category_id, dateline_city, dateline_at, priority, is_breaking, language, embargo_until, tags`.
5. **`audit_logs` rows now carry** `ip` (inet), `user_agent`, `correlation_id` (`X-Correlation-Id` request header ?? `Str::uuid()`), `diff` (before/after arrays).

**Version bump rule (UNIQUE-safe):** `story_versions` has UNIQUE `(story_id, version)`. Since publish must snapshot (AGENTS.md §2.1) and `stories.version` only bumps on content saves today, **`StoryService::transition()` bumps `stories.version` by 1 and snapshots at the new number**. This also strengthens optimistic concurrency: a state change invalidates concurrent edits (stale → 409), which is the desired behavior.

**Restore semantics (non-destructive):** `restore()` writes a NEW `story_versions` row with restored content and bumps `stories.version`; it never mutates or deletes history. Allowed only in `draft | in_review | changes_requested` — published/archived/killed stories go through the corrections/unpublish flow (FR-NWS-016), never silent restore. Requires `stories.can_edit` + soft lock + optimistic version (409 on stale). Emits `story_events` action `restored` + `audit_logs` entry.

---

## 4. UI / Interface Contracts

| Surface | Contract |
|---------|----------|
| Story view `/admin/news/{id}` | Adds **Timeline** section (events with actor, action label, payload context, timestamps — newest first) and **Versions** section (v#, author, time). Gated `history.can_view`. FR-NWS-019. Data already eager-loaded — zero extra queries. |
| Diff viewer | Two-version compare: field rows for meta, word-level diff for `body_html`. **Server-rendered** by `RevisionService::diff` in Blade — no client JS diff lib, no `innerHTML` sync (avoids wire:ignore anti-pattern). |
| Restore action | Confirm modal → `RevisionService::restore` → 409 handling on stale → success re-rendered from server truth (no fake success, AGENTS.md §10). |
| Wizard History modal | Saved story → server-side version list (+ restore-to-draft pre-publish). Never-saved draft → existing localStorage `unb_rev_v1` flow unchanged. Per app-data README mapping table. |
| Audit browser `/admin/audit` | Filterable (actor, action, entity type, entity id, date range), paginated 25, per-entity drill via `?entity_type=&entity_id=` deep-link from story view. Gated `audit.can_view`. |

---

## 5. Security & RBAC (DEC-012)

Two new modules in `role_permissions` (seed data; `can_view` is the only active column — history is read + restore-via-edit-permission, audit is read-only):

| Role | `history.can_view` | `audit.can_view` | Rationale |
|------|-------------------|------------------|-----------|
| Admin | 1 | 1 | Full control |
| Editor | 1 | 0 | Newsroom chain-of-custody |
| Strategist | 1 | 0 | Reviews desk work |
| Admin Report | 1 | 1 | Read-only management reporting (FR-NTF-003 "viewable by admins") |
| Business Team | 0 | 0 | No newsroom access |
| Client Bangla | 0 | 0 | Client portal login — internal newsroom data never exposed |
| Uploader-Bangla / Uploader-English | 1 | 0 | See own desk history, cannot publish |

Server-side `RbacService.assertCan(user, 'history'|'audit', 'view')` on every new surface; UI hides but API says no (AGENTS.md §6). Restore additionally requires `stories.can_edit` + lock/optimistic-version semantics.

---

## 6. Task Decomposition Outline (Planner handoff)

Backend first, then UI, then hardening. All task files in `docs/tasks/` (ACT-DP format).

| # | Task | Deps | Layer |
|---|------|------|-------|
| `M10-HIST-001` | RBAC `history` + `audit` modules (seeder, matrix UI, DEC-012) | — | Backend |
| `M10-HIST-002` | Full-field snapshots + snapshot-on-transition (`RevisionService::snapshot`) | — | Backend |
| `M10-HIST-003` | `RevisionService::diff` + `restore` (non-destructive, lock-safe) | 002 | Backend |
| `M10-HIST-004` | `story_events` payload enrichment + spec action vocabulary | 002 | Backend |
| `M10-HIST-005` | Sensitive actions → `audit_logs` (ip/ua/correlation/diff) + `AuditQueryService` | 001, 004 | Backend |
| `M10-HIST-006` | Story view: Timeline + Versions sections | 001, 004 | UI |
| `M10-HIST-007` | Diff viewer + restore action (story view) | 003, 006 | UI |
| `M10-HIST-008` | Wizard History modal → server-side versions | 003 | UI |
| `M10-HIST-009` | Global audit browser `/admin/audit` | 005 | UI |
| `M10-HIST-010` | E2E hardening + knowledge-inventory sync + full CI gates | 001–009 | Verification |

Quality gates per AGENTS.md §10 apply per task; schema parity must stay green (no migration drift expected — assert in 010).
