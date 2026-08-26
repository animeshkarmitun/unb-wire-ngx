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
