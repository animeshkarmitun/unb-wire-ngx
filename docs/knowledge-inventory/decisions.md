# Architecture Decision Log (ADR) — UNB Wire

> Permanent record of key architectural, domain, and technical decisions.
> Follows the format: Context → Decision → Consequences → Status.
> **Hygiene rule:** Never silently rewrite an old decision. Add a new `DEC-NNN` that supersedes the prior one.

---

## Decision Index

| Decision ID | Title | Date | Status |
|-------------|-------|------|--------|
| `DEC-001` | Multi-Agent Development Workflow Architecture | 2026-08-25 | Accepted |
| `DEC-002` | Framework & Stack Selection: Laravel 11/12 + Filament v3 | 2026-08-25 | Superseded by `DEC-006` |
| `DEC-003` | Immutable Editorial Revision Tracking Model | 2026-08-25 | Accepted |
| `DEC-004` | Sanctum-Based Token Scoping for Wire Subscribers | 2026-08-25 | Superseded by `DEC-007` |
| `DEC-005` | Queue-Backed Webhook & Push Distribution System | 2026-08-25 | Accepted |
| `DEC-006` | v1 Stack Lock per app-data NFR §16 | 2026-08-26 | Accepted |
| `DEC-007` | Canonical Domain Contracts from app-data FRD & DB Design | 2026-08-26 | Accepted |
| `DEC-008` | Field Apps (MoJo / Photo Desk PWA) Deferred | 2026-08-26 | Accepted |

---

## `DEC-001`: Multi-Agent Development Workflow Architecture

### Context
Engineering requires a systematic, verifiable development process for AI agents and human developers, minimizing hallucinations, scope creep, and breaking regressions.

### Decision
Adopt the multi-role pipeline (Architect, Planner, Coder, Reviewer, QA, Smoke) with ACT-DP task decomposition, independent reviewer subagents (`unb-wire-reviewer`), local quality hooks, and live pre-commit smoke tests.

### Consequences
- High traceability across all tasks (`docs/tasks/`).
- Prevents unverified commits.
- Reviewer subagent in isolated context prevents confirmation bias.

---

## `DEC-002`: Framework & Stack Selection

### Context
Need a high-performance, maintainable backend for managing editorial desks, rich content, API delivery, and subscriber portals.

### Decision
Use Laravel with Filament v3 for editorial administration, MySQL/MariaDB for storage, Redis for wire caching and queue processing, and Blade + Tailwind for client portals.

### Consequences
- Rapid development of admin workflows via Filament Resources.
- Robust Eloquent and Queue ecosystem.

---

## `DEC-003`: Immutable Editorial Revision Tracking Model

### Context
News agencies require audit trails and legal compliance for editorial modifications, corrections, and retractions.

### Decision
Implement an immutable `article_revisions` table that captures article snapshots upon every state transition to `published` or subsequent edit.

### Consequences
- Full diff capability between editorial versions.
- Safe rollback mechanism.

---

## `DEC-004`: Sanctum-Based Token Scoping for Wire Subscribers

### Context
Wire API subscribers require API tokens with distinct capabilities (rate limits, category restrictions, webhook registration).

### Decision
Use Laravel Sanctum abilities to encode subscriber tier capabilities into API tokens, enforced via route middleware and policies.

### Consequences
- Lightweight token authentication without OAuth2 complexity.
- Granular capability checks on wire endpoints.

---

## `DEC-005`: Queue-Backed Webhook & Push Distribution System

### Context
Publishing a breaking wire story must immediately notify hundreds of subscriber endpoints without blocking the editorial HTTP request cycle.

### Decision
Dispatch asynchronous jobs via Laravel Queues (Redis) to deliver webhook payloads and push alerts with exponential backoff retries.

### Consequences
- Fast response times on editorial publish actions.
- Resilient delivery against subscriber server downtime.

---

## `DEC-006`: v1 Stack Lock per app-data NFR §16

**Supersedes `DEC-002`.**

### Context
The approved UI prototypes and engineering contract arrived in `app-data/`
(`README.md`, `v1-non-functional-requirements.md`, `v1-functional-requirements.md`,
`v1-database-design.md`). NFR §16 locks the v1 stack based on the real load model;
the earlier Filament + MySQL assumption no longer holds.

### Decision
- **Backend:** Laravel modular monolith with **MVC + service + repository** layering
  (NFR §16.1): thin controllers/Livewire, §15 services own business logic and
  transactions, repositories are the only place Eloquent is used, models stay dumb.
- **Admin (newsroom, DAM, RBAC, distribution):** Blade + Livewire 3 + Alpine,
  converted from the prototypes in `app-data/`. **No Filament.**
- **Client portal:** decoupled **Next.js**, CDN-hosted, browser-direct Meilisearch
  with tenant tokens.
- **Field apps:** static PWAs (build deferred — see `DEC-008`).
- **Database:** PostgreSQL 16 (primary + read replica).
- **Search:** Meilisearch, `main` + `archive` indexes, synced via `index_outbox`.
- **Queue/workers:** Redis + Horizon; **realtime:** Reverb; **media:** S3-compatible
  + CDN + tus resumable uploads.

### Consequences
- No Filament resources; admin is built as Blade components + Livewire islands.
- PostgreSQL-only features are expected: `timestamptz`, monthly RANGE partitions,
  JSONB, `citext`.
- Portal and admin share only the REST API; Livewire never calls the internal API.

---

## `DEC-007`: Canonical Domain Contracts from app-data FRD & DB Design

**Supersedes `DEC-004`.**

### Context
`app-data/v1-functional-requirements.md` (86 FRs) and `v1-database-design.md`
define the editorial workflow states and the subscription model; the early docs
(domain.md lifecycle, tier table) predate them and conflict.

### Decision
- **Editorial states:** `draft → in_review → changes_requested → approved →
  published`, plus terminal `killed` and `archived` (FR-NWS-010). "Scheduled
  publish" is `embargo_until` / future-dated publish, **not** a workflow state.
  Direct publish from draft is allowed for roles with publish permission.
- **Subscription model:** packages with a declarative `entitlement_filter` (JSONB)
  replace fixed tiers. One entitlement definition compiles to delivery fan-out,
  portal visibility, and the Meilisearch tenant-token filter (FR-DST-002) — the
  three can never drift.
- **Client API access:** hashed `client_api_keys` (sha256, scoped, per-key rate
  limit, rotation with overlap). Sanctum remains for staff/field-device tokens only.
- `DEC-003` (immutable revisions → `story_versions`) and `DEC-005` (queue-backed
  fan-out → Horizon jobs) stand unchanged.

### Consequences
- `domain.md`, `data-model.md`, the `unb-wire-domain` skill, and the
  `unb-wire-reviewer` agent were updated to these contracts in the same change.

---

## `DEC-008`: Field Apps (MoJo / Photo Desk PWA) Deferred

### Context
Product owner decision (2026-08-26): the offline-first field PWAs are not needed
in the current build scope.

### Decision
- FR-FLD-001…010 and `Modules/Field` (`AssignmentService`, `FieldIngestService`,
  `OutboxSyncService`) are out of scope for now, along with `DeviceRegistry` /
  the `devices` table and the `assignments` table.
- Admin-side media review (`MediaReviewService`, approval queue UI) remains,
  fed by desk uploads (FR-MED-009) landing in pending review. The field-intake
  tab will only populate once field apps ship.
- `upload_sessions` (tus) stays — desk uploads use the same resumable path.
- `mojo-field-desk.html` / `photo-field-desk.html` prototypes are reference-only
  until the PWA track opens.

### Consequences
- Migration task list (DB design §10) drops `assignments`; `devices` moves out of
  the auth-base task.
 - No field-app notification recipients; editorial notifications scope to desk
   staff only.

---

## `DEC-009`: M4-M5 Feature Completion — Editorial Hardening, Search, Media, API, Billing, Portal, AI, Ops

### Context
v1 required RBAC/editorial 409+state machine, Meilisearch tenant-token with single entitlement source, S3/tus/derivatives+CDN presigned, scoped/rotatable/rate-limited client API keys with cursor feed, MRR/invoices, portal graceful degradation, AI kill-switch+budgets+auto-publish allowlist, and Horizon/embargo/archival ops.

### Decision
- `StoryService` with optimistic `version` 409, `TRANSITIONS` map, AI/embargo gates, `take_over` chain-of-custody, `index_outbox` transaction.
- `EntitlementResolver`+`TenantTokenIssuer` (HS256 JWT, 30m, searchRules main/archive/media, Cache 5m).
- `PresignedUrlService`+`GenerateDerivatives` (thumb/preview/large webp) + `TusController` (Upload-Offset/Length).
- `ApiKeyService` issue/rotate/revoke (sha256, two-key overlap) + `EnsureClientApiKey` (scope+60rpm→429) + cursor `?since` ISO feed.
- `BillingService` MRR + idempotent `invoices`/`invoice_lines` per client per month.
- Portal `search.ts` 5s timeout fallback to feed ISR (60s Cache-Control, STALE-IF-ERROR).
- `AiService` desk toggle + monthlyCap sum guard, `StoryService` autoCats allowlist.
- Horizon queues [default,outbox,fanout,derivatives,billing], `preventLazyLoading` in prod, `embargo-lift`/`archive-sweep` write `index_outbox` with fanout.

### Consequences
- Single entitlement source drives delivery+portal+search; no drift.
- AI publish blocked unless breaking or allowlisted routine cats.
- Feed tag-cache invalidated on publish; N+1 blocked in prod.

---

## `DEC-010`: v1 Perf & Security Lock

### Context
Admin routes lacked server-side RBAC; audit was append-only in spec but not enforced; perf had N+1 risks on feed.

### Decision
- All `/admin/*` now `rbac:module,action` (EnsureRbac alias) + global `preventLazyLoading` / `preventAccessingMissingAttributes` / `prohibitDestructiveCommands` in prod.
- `audit_logs` append-only (no UPDATE/DELETE grants on pgsql, app only INSERT).
- Feed portal + v1 feed use `Cache::remember` 60s with `Cache-Control public max-age=60`; Horizon `withoutOverlapping` on schedulers.

### Consequences
- UI hides buttons but API still 403; pgsql grants enforce audit immutability.
- FE CDN caches feed; schedulers never overlap.

---

## `DEC-011`: Schema-Code Parity Remediation (M9-SCHEMA batch)

### Context
Audit `docs/schema-code-mismatch-report.md` (2026-08-29, 25 mismatches) found drift between `v1-database-design.md` and `database/migrations/*` + `app/Models`. Critical: `users.role_id` FK was SET NULL vs RESTRICT, append-only grants missing, `assignments`/`media_batches.assignment_id` absent per §6, monthly RANGE partitioning unimplemented, `devices` contradicted DEC-008. Medium: `timestamptz` bare timestamps in skeleton tables, extra `updated_at` cols, `is_internal` patch, `invoices` extra tables, invented CHECKs, `word_count` nullability. Low: index DESC, model casts, factory CHECK coverage, seeder encoding.

### Decision
- `users.role_id` FK → `RESTRICT` (migration `2026_08_29_200001`). Nullable retained for sqlite test compat but `ON DELETE RESTRICT` enforced on pgsql.
- Append-only grants enforced via `2026_08_29_200003_append_only_grants.php` (`REVOKE UPDATE,DELETE` on `story_notes,story_events,deliveries,audit_logs,downloads`).
- `assignments` reintroduced (`2026_08_29_200002`) with `media_batches.assignment_id` FK — aligns with design §6; deferral in DEC-008 superseded for schema parity (table exists but unused until field apps return).
- `devices` retained despite DEC-008 — desk upload audit + `personal_access_tokens.device_id` require it; DEC-008 amended to defer only `Modules/Field` services, not the table.
- `invoices`/`invoice_lines` (M5) + `story_notes.is_internal` (M4) ratified into `v1-database-design.md` §7/§5.
- Invented CHECKs ratified: `packages.status IN ('active','archived')`, `client_packages.status IN ('active','expired','cancelled')`, `client_channels.status IN ('active','paused','disabled')`.
- `timestamptz` business tables fixed (`password_reset_tokens`, `failed_jobs` → `timestamptz`); `jobs`/`job_batches` epoch ints documented as framework tables, excluded from business-table `grep==0` rule.
- `word_count` kept nullable, cast `integer`, documented as app-set (not generated).
- `stories_status_published_at_index` recreated as `(status, published_at DESC)` for feed planner.
- Model casts added: `Story.version/word_count=>integer`, `InvoiceLine` decimals; factories expanded to cover all enum values.

### Consequences
- Schema now matches canonical design + DEC-011; `docs/schema-code-mismatch-report.md` appendix marks C/M/L as resolved or ratified with migration IDs.
- Partitioning remains deferred by design §9 guidance (revisit at ~5M rows); no RANGE partitions in v1 — documented, not implemented.
- `migrate:fresh --seed` green on pgsql+sqlite; `append_only_grants` is pgsql-only (sqlite no-op).

---

## `DEC-012`: RBAC `history` + `audit` Permission Modules (M10-HIST)

### Context
`role_permissions` had 8 modules (`stories…ai`). History visibility was uncontrolled: anyone who could view a story would implicitly see its timeline/versions once rendered (FR-NWS-019), and the Roles Manager "Activity" tab had no permission gate. FR-NTF-003 requires per-entity audit history "viewable by admins" only. Design §`role_permissions` module list is open-ended ("…"), so this is seed data, not a schema change.

### Decision
- Two view-only modules (`actions => ['view']` in RolesManager matrix):
  - `history` — story timeline + version history + diff visibility. Granted: Admin, Editor, Strategist, Admin Report, Uploader-Bangla, Uploader-English. Denied: Business Team, Client Bangla (newsroom-internal data, never client-facing).
  - `audit` — `audit_logs` browsing (global browser + per-entity drill). Granted: Admin, Admin Report (read-only management reporting). All others denied.
- Restore action additionally requires `stories.can_edit` + soft-lock + optimistic version (enforced in `RevisionService`, M10-HIST-003) — `history.can_view` alone never mutates.
- RolesManager `uploader`/`editor` presets grant `history.view`; `audit` stays manual-grant only.
- Logged in `RoleSeeder` matrix (idempotent `updateOrInsert` — existing installs pick it up on next `db:seed`).

### Consequences
- Server-side `RbacService.assertCan(user, 'history'|'audit', 'view')` gates every history/audit surface (M10-HIST-006…009); UI hides but API 403s.
- `php scripts/schema-parity-check.php` unaffected — no migration, no model change.
