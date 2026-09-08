# Architecture Overview — UNB Wire

> Technical architecture per the locked stack (`DEC-006`, canonical:
> `app-data/v1-non-functional-requirements.md` §15–16).

---

## 1. System Architecture

```
[ Client users ]            [ Newsroom staff ]          [ Client systems ]
   (Next.js portal,             (Blade + Livewire          (REST API / FTP /
    CDN-hosted)                  admin, same app)           webhooks)
        │                            │                          │
        ▼                            ▼                          ▼
┌──────────────────────────────────────────────────────────────────────┐
│                 Laravel modular monolith (API + admin + workers)     │
│  Modules/: Stories · Media · Distribution · Clients · Portal ·       │
│            Access · Ai · Notifications   (Field — deferred, DEC-008) │
│  Layering per module (NFR §16.1):                                    │
│    Controllers/Livewire (thin) → Services (business logic + tx) →    │
│    Repositories (only Eloquent) → Models (dumb)                      │
│  Auth: session (staff admin) · hashed client_api_keys · Sanctum      │
│        (field devices — deferred)                                    │
└──────────────────────────────────────────────────────────────────────┘
     │            │              │               │             │
     ▼            ▼              ▼               ▼             ▼
 PostgreSQL 16   Redis      Meilisearch      S3 + CDN      Reverb
 (primary +    (cache +    (main + archive  (originals +  (admin live
  read replica)  Horizon)    indexes)        derivatives)  updates)
```

- **Portal** is the only decoupled frontend (highest-traffic human surface);
  browser-direct Meilisearch with tenant tokens — search never hits Laravel.
- **Field apps** (static PWAs) are deferred (DEC-008); prototypes in `app-data/`
  are reference-only.

---

## 2. Backend layering rules (NFR §16.1)

1. **Controllers / Livewire components (thin):** validate, authorize
   (`RbacService.assertCan`), call one service, shape the response. No queries, no
   business rules. Livewire never calls the internal REST API.
2. **Services** (the NFR §15/§15.1 inventory — 48 domain classes): all business
   logic; own transactions (content write + `index_outbox` row commit together).
   Cross-module talk is service-to-service, never model-to-model.
   - **`RevisionService`** (M10-HIST): `snapshot(Story, User)`, `diff(StoryVersion a, StoryVersion b)`,
     `restore(Story, int version, User, ?int expectedVersion)` — full-field version snapshots,
     field-level + word-level diff, non-destructive restore with optimistic version.
   - **`AuditQueryService`** (M10-HIST): `forEntity(type, id)`, `search(filters)` — paginated
     read-only queries over `audit_logs` for admin audit browser + per-entity drill.
3. **Repositories (one per aggregate):** the ONLY place Eloquent/query-builder is
   used — read-replica routing, partition-aware queries (date bounds on
   `deliveries`/`audit_logs`), eager loading, cursor pagination. Concrete classes;
   interfaces only where a real swap exists (`SearchService`, storage disk).
4. **Models (dumb):** relations, casts, scopes, optimistic-lock increment. No
   business logic, no observers.

Jobs call **services**, never controllers — a job is a deferred service call with
an idempotency key. Caching lives in services (`CacheAside`); controllers and
repositories never cache.

---

## 3. Async jobs & distribution (NFR §6–7)

- **House rule:** anything >100ms or touching SMTP/FTP/S3/CDN/search runs in a
  Horizon worker. The request validates → writes state → enqueues → returns.
- **Fan-out:** publish/approve → `FanoutPlanner` writes one `deliveries` row per
  entitled client-channel (idempotency key, embargo hold) → `DeliveryService`
  attempts with backoff; auto-pause after N consecutive failures; `payload_hash`
  recorded per attempt.
- **Scheduler (defined in Dhaka wall-clock, executed as UTC instants):** embargo
  lifts (±60s), archival sweep, digests, backup drills.
- **Search sync:** `index_outbox` → indexer workers → Meilisearch `main`/`archive`;
  lag budget ≤5s after publish; full reindex from Postgres must stay a tested,
  scripted operation.

---

## 4. Caching & performance

- Read path: PostgreSQL read replica + Redis hot feed + CDN for media; published
  stories are immutable → aggressive cache, versioned derivative URLs with infinite
  TTL.
- `CacheAside` get-or-load with tag-based invalidation on publish/update (in the
  service layer).
- Latency budgets (NFR §1/§5): feed API p95 < 200ms; portal/admin search p95
  < 300ms; publish → client feed visible within 60s.

---

## 5. Security topology (NFR §8)

- RBAC: `role_permissions` matrix → `RbacService.assertCan` on every endpoint;
  UI hides what the role can't do, the server still says no.
- Client API keys: sha256-hashed, scoped, per-key rate limit, rotation overlap.
- Channel secrets stored as vault references only (`credential_ref`/`secret_ref`).
- Audit log: append-only (DB-grant enforced), before/after diffs, correlation ids,
  7-year retention.
- Presigned, short-lived URLs for private media; every download ledgered.
