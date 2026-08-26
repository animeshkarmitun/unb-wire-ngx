# AGENTS.md — UNB Wire News Service & Publishing Platform

Behavioral guidelines, architecture standards, quality gates, and AI protocols for **UNB Wire** (Laravel).

> **Required reading order for agents:**
> 1. This `AGENTS.md` (warnings, standards & conventions)
> 2. `app-data/README.md` + `app-data/v1-non-functional-requirements.md` + `app-data/v1-functional-requirements.md` — **canonical product contracts** (approved UI prototypes + engineering contract). On disagreement: NFR wins on behavior, prototype wins on look & feel. Field-app PWAs are deferred (`DEC-008`).
> 3. `docs/knowledge-inventory/domain.md` (domain summary of the app-data contracts)
> 4. `docs/knowledge-inventory/decisions.md` (Architecture Decision Log)
> 5. `app-data/v1-database-design.md` + `docs/knowledge-inventory/architecture.md` / `data-model.md` (if modifying routing, schema, or services)
> 6. For non-trivial work: `docs/workflow.md` and `docs/task-decomposition-protocol.md` (see §0)

---

## 0. Mandatory Workflow (All Work)

**Applies to every agent request** — features, bugfixes, refactors, cleanups, tests, docs.

| Size | Process |
|------|---------|
| **Trivial** — ≤1 file, a few lines, no editorial/status/auth/schema change | Surgical edit + quality gates (§10) |
| **Non-trivial** — multi-file, domain logic, editorial/wire/subscription/API/schema, or any vague "refactor/improve" request | Full pipeline: task file → plan with checks → implement → verify → **live smoke** → **`unb-wire-reviewer`** → PR handoff |

Pipeline detail: [`docs/workflow.md`](docs/workflow.md).
Agent skills: [`.cursor/skills/unb-wire-workflow/`](.cursor/skills/unb-wire-workflow/), [`.cursor/skills/unb-wire-domain/`](.cursor/skills/unb-wire-domain/).
Task format: [`docs/task-decomposition-protocol.md`](docs/task-decomposition-protocol.md).
Task index: [`docs/tasks/README.md`](docs/tasks/README.md).
Refactor rules: [`docs/refactor-guidelines.md`](docs/refactor-guidelines.md).
Prompt templates: [`docs/prompt-guide.md`](docs/prompt-guide.md).
**Before commit (runtime changes):** [`docs/workflow/live-test-runbook.md`](docs/workflow/live-test-runbook.md) — clear compiled caches, then smoke the affected routes/roles.

**Git handoff:** After gates pass → **commit → push → PR** (do not re-ask). Merge when PR CI is green per [`docs/workflow/git-strategy.md`](docs/workflow/git-strategy.md). Agent merges to `main` only when explicitly instructed.

**Agents must not** skip task decomposition for non-trivial work, expand scope beyond the ask, or commit editorial/feed/API/model changes without a live smoke pass (recorded in Completion Notes).

---

## 1. Project Identity

| Attribute | Value |
|-----------|-------|
| **Name** | UNB Wire |
| **Type** | News Agency Wire Service & Real-Time Editorial Distribution Platform |
| **Framework** | Laravel (modular monolith, MVC + service + repository — NFR §16.1), PHP ^8.2 |
| **Admin / Editorial** | Blade + Livewire 3 + Alpine (converted from `app-data/` prototypes; **no Filament** — DEC-006) |
| **Client Portal** | Next.js, decoupled, CDN-hosted; browser-direct Meilisearch with tenant tokens |
| **Field Apps** | Static PWA — **deferred, not in current scope** (DEC-008) |
| **Auth** | Session (staff admin); hashed `client_api_keys` (scoped, rate-limited); Sanctum reserved for field devices (deferred) |
| **Database** | PostgreSQL 16 (primary + read replica; `timestamptz`, JSONB, monthly partitions) |
| **Search** | Meilisearch (`main` + `archive` indexes) via `index_outbox` workers |
| **Cache & Queue** | Redis + Horizon; Realtime: Reverb; Scheduler: embargo lifts, archival sweep |

---

## 2. Critical Business Logic & Invariants

### 2.1 Editorial Lifecycle
Stories transition through strictly validated states (FR-NWS-010, DEC-007):
```
draft → in_review → changes_requested → approved → published   (+ killed, archived)
```
- **No `scheduled` state:** embargo/scheduled publish is `embargo_until` (explicit-tz input, stored UTC, worker-enforced ±60s).
- **Concurrency:** soft edit lock (`locked_by`/`locked_at`) + optimistic `version`; stale save → 409 + merge prompt, never silent overwrite. Take-over force-releases the lock and records chain-of-custody.
- **Revision History:** every save/publish writes an immutable `story_versions` snapshot; every transition writes a `story_events` audit record.
- **Internal notes** are newsroom-only and immutable — never on client APIs/feeds/search.
- **Breaking News Flag:** urgent/breaking dispatches trigger high-priority fan-out and portal flags.

### 2.2 Packages, Entitlements & Client Access (DEC-007 — tiers replaced)
- **Packages** declare a declarative `entitlement_filter` (languages, categories, media kinds). **One definition drives everything:** delivery fan-out, portal visibility, and Meilisearch tenant-token filters compile from the same source (FR-DST-002).
- **Subscriptions** attach clients to packages with start/end dates; overlapping subscriptions union entitlements; expiry cuts delivery + portal + API at once.
- **Delivery:** at-least-once fan-out with idempotency keys, backoff, auto-pause after N failures, `payload_hash` per attempt. Kills/corrections fan out to every client that received the item.
- **Client API keys:** sha256-hashed (raw shown once), scoped, per-key rate limit (default 60 rpm), rotation with two-key overlap.
- **AI guardrails:** per-desk toggles + monthly token budgets, AI-touched field markers drive a publish gate, auto-publish default OFF (allowlisted routine categories only), global kill switch — all audited (FR-AI-001…010).

---

## 3. Think Before Coding

- **State assumptions explicitly.** If requirements are ambiguous, clarify before implementing.
- **Surface tradeoffs.** If a simpler approach exists, say so.
- **No silent decisions.** When multiple viable paths exist, present them.
- **If confused, stop.** Name what is confusing and ask.

---

## 4. Simplicity First

- **No features beyond what was asked.** Defer nice-to-haves to future milestones.
- **No speculative abstractions.** A trait or service class must be used at least twice to exist.
- **No unnecessary configurability.** If a setting wasn't requested, use a sensible default.
- **No error handling for impossible scenarios.** Validate real inputs, not theoretical ones.
- **If it feels overcomplicated, rewrite it** until a senior engineer would approve.

---

## 5. Surgical Changes

- **Touch only what the request requires.** Do not "improve" adjacent code or reformats.
- **Match existing style**, even if you'd do it differently.
- **Do not refactor unrelated code.** Mention dead code; do not delete it unless asked.
- **Clean up your own orphans.** Remove imports, variables, or methods made unused by *your* changes.

**The test:** Every changed line should trace directly to the user's request.

---

## 6. Laravel & Coding Conventions

### Architecture & Style
- **Use Eloquent:** Raw SQL only when performance is proven to require it.
- **Form Request Classes:** Use dedicated Form Requests for public/subscriber API validation.
- **Livewire + Blade Admin:** Admin surfaces are one Blade layout + Livewire components converted from the `app-data/` prototypes (design tokens §2 there are law). Components stay thin — validate, authorize, call one service; no queries, no business rules (NFR §16.1).
- **RBAC Authorization:** Server-side scope checks via `RbacService.assertCan(user, module, action)` backed by the `role_permissions` matrix — on every endpoint. The UI hides buttons; the API must still say no.
- **Migrations:** All schema changes must be migrations; never manual DB edits. Follow `app-data/v1-database-design.md` §10 order and conventions (`timestamptz`, varchar+CHECK enums, ULID `public_id`).
- **Storage:** Media originals/derivatives on S3-compatible object storage behind a CDN (immutable, versioned derivative URLs); private media served via short-lived presigned URLs with download ledgering.

---

## 7. Data, Performance & Caching

- **Eager-Load by Default:** No N+1 queries on article lists, wire feeds, category index, or media attachments. Enforce `Model::preventLazyLoading(! app()->isProduction())`.
- **Wire Feed Caching:** Cache live feed queries (Redis/File) with tag-based invalidation upon article publish/update.
- **Database Transactions:** Use `DB::transaction()` for multi-table updates (e.g., article publish + revision snapshot + feed dispatch).
- **Index Optimization:** Ensure indexes on `(status, published_at)`, `(category_id, published_at)`, and `uuid`.

---

## 8. Security

- **Validate all uploads** (images, docs, video) by strict MIME type, extension, and file size.
- **Purify Rich Text:** Strip unsafe HTML tags from article body before database insertion.
- **Rate-Limit Public & API Endpoints:** Protect login, search, subscription webhooks, and news feed APIs.
- **Hash Sensitive Tokens:** API tokens and credentials must be hashed and never exposed in logs.
- **CSRF & Parameterized Queries:** Standard CSRF protection on forms and Eloquent parameter binding.

---

## 9. Goal-Driven Execution

Before implementing, state the step-by-step plan:

```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Do not say "make it work." Define verifiable success criteria.

---

## 10. Quality Gates

Before considering any task done:

- [ ] `php artisan migrate:fresh --seed` runs without errors (if schema touched).
- [ ] `php artisan test` passes (or new tests exist for the change).
- [ ] No PHP syntax errors (`php -l` on all changed PHP files).
- [ ] No N+1 queries on changed pages or API endpoints.
- [ ] Admin / editorial panel loads without authorization errors for assigned roles.
- [ ] Wire feeds and public portal render correctly.
- [ ] **Runtime changes live-smoked** per `docs/workflow/live-test-runbook.md` (caches cleared, routes hit as relevant role) and recorded in Completion Notes.
- [ ] Code follows existing style and touches only requested files.

---

## 11. AI-Assisted Development Protocol

### 11.1 Non-Negotiable Rules
1. **Never accept AI output without running it locally.**
2. **Always write tests before or alongside implementation.**
3. **Keep prompts atomic (one deliverable per context window).**
4. **Challenge assumptions — verify edge cases and failure paths.**
5. **Own architecture, outsource syntax.**
6. **Version control on feature branches; PR before merge.**

### 11.2 Commit Message Convention (Conventional Commits)
```
<type>(<scope>): <short description> [<task-id>]
```
Examples:
- `feat(wire): add breaking news webhook dispatcher [WIRE-002]`
- `fix(editorial): resolve concurrency lock expiration bug [EDIT-005]`
- `docs(api): update wire feed openapi spec [DOC-001]`
- `test(auth): add subscriber tier permission tests [AUTH-003]`

---

## 12. Documentation & Knowledge Inventory Sync Gate

Update documentation **in the same commit/PR as code** whenever changes affect:
- **Business / Wire rules** → `docs/knowledge-inventory/domain.md`
- **Architecture decisions** → `docs/knowledge-inventory/decisions.md` (`DEC-NNN`)
- **System patterns / Tech stack** → `docs/knowledge-inventory/architecture.md`
- **Database schema** → `docs/knowledge-inventory/data-model.md`
- **Agent-critical warnings** → `AGENTS.md`

Full gate: `docs/workflow.md` §10.
