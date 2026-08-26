---
name: unb-wire-domain
description: >-
  UNB Wire domain logic, editorial workflow states, package entitlements and
  distribution, concurrency locking, revision history tracking, and client API keys.
  Load this skill when working on story publishing, editorial review, wire REST APIs,
  client entitlements, webhooks, or feed caching.
---

# UNB Wire — Domain Knowledge Skill

Domain rules and architectural invariants for the UNB Wire news service platform.

## Companion Documents

| Doc | Purpose |
|-----|---------|
| `app-data/v1-functional-requirements.md` | **Canonical behavior** — 86 FRs with acceptance criteria |
| `app-data/v1-non-functional-requirements.md` | **Canonical engineering contract** (search, jobs, security, time, AI, stack) |
| `app-data/v1-database-design.md` | **Canonical schema** (PostgreSQL 16) + migration task order |
| `docs/knowledge-inventory/domain.md` | Domain glossary and editorial state machine (summary of the above) |
| `docs/knowledge-inventory/decisions.md` | Architecture Decision Log |
| `docs/knowledge-inventory/architecture.md` | Layering, caching, queue workers, distribution architecture |
| `docs/knowledge-inventory/data-model.md` | Schema conventions + pointer to the canonical DB design |

---

## Core Invariants

1. **State Machine Integrity (FR-NWS-010, DEC-007):**
   - Stories transition strictly: `draft` → `in_review` → `changes_requested` → `approved` → `published`, plus terminal `killed` and `archived`. Direct publish from draft is allowed for roles with publish permission. "Scheduled publish" is `embargo_until` (explicit-tz → UTC), **not** a workflow state.
   - Every transition is permission-checked server-side and written to `story_events`; never skip `story_versions` snapshots on save/publish.

2. **Concurrency & Handover:**
   - Soft edit lock (`locked_by`/`locked_at`) plus optimistic `version` check — a stale save is rejected 409 with a merge prompt, never a silent overwrite. Take-over transfers ownership, force-releases the lock, and records chain-of-custody (FR-NWS-013/014).

3. **Index & Cache Invalidation:**
   - Every content write commits together with an `index_outbox` row (transactional outbox → Meilisearch `main`/`archive`). Feed/portal caches invalidate via `CacheAside` tags on publish/update.

4. **Entitlement Boundaries (FR-DST-002, DEC-007):**
   - No fixed tiers — packages carry a declarative `entitlement_filter`; one compiled definition drives delivery fan-out, portal visibility, and Meilisearch tenant-token filters, so they can never drift.
   - Client API keys are sha256-hashed, scoped, and rate-limited per key (raw key shown once).
