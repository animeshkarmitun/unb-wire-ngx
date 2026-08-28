# Task: M2-DB-007 — Distribution: packages, package_media, client_packages, client_channels, deliveries

**Status:** ✅ Completed
**Dependencies:** M2-DB-006
**Parent ADR:** v1-database-design §7 + §10.7

---

## 1. Contract (What)

- **Inputs / Validation:** No API inputs. Schema must match `v1-database-design.md §7` exactly.
- **Outputs / Response:** Migrations green on PostgreSQL 16, models cast correctly, `php artisan migrate:fresh --seed` passes.
- **Authorization:** N/A (entitlements drive `RbacService` elsewhere).

---

## 2. Logic (How)

1. Create `packages` — `id` PK, `code` varchar(32) unique, `name` varchar(120), `kind` varchar(12) CHECK news/photos/bundle, `description` text null, `entitlement_filter` jsonb, `price_monthly` numeric(10,2) null, `status` varchar(12) default active CHECK active/archived, `created_at`/`updated_at` timestamptz.
2. Create `package_media` pivot — `package_id` FK cascade, `asset_id` FK cascade, `added_at` timestamptz default now(), PK (package_id, asset_id).
3. Create `client_packages` — `id` PK, `client_id` FK cascade, `package_id` FK restrict, `starts_at` timestamptz, `ends_at` timestamptz null, `status` varchar(12) default active, `created_at` timestamptz, UNIQUE (client_id, package_id, starts_at).
4. Create `client_channels` — `id` PK, `client_id` FK cascade, `type` varchar(12) CHECK api/ftp/webhook, `config` jsonb, `status` varchar(12) default active, `last_success_at` timestamptz null, `failure_count` int default 0, `created_at`/`updated_at` timestamptz.
5. Create `deliveries` — `id` PK, `deliverable_type` varchar(8) CHECK story/media, `deliverable_id` bigint, `client_id` FK clients, `channel_id` FK client_channels, `status` varchar(12) CHECK queued/sent/delivered/failed/skipped_entitlement, `attempt_count` int default 0, `idempotency_key` varchar(80) unique, `payload_hash` char(64), `response_code` int null, `error` text null, `sent_at`/`delivered_at` timestamptz null, `created_at` timestamptz. Partitioning deferred — v1 single table with indexes (client_id, created_at DESC) and partial (status, created_at) WHERE status='failed'. Range partitioning to be added at ~5M rows per §9.
6. varchar+CHECK pgsql-only, timestamptz, jsonb, numeric.

---

## 3. Context (Where)

- **Files to Create / Modify:**
  - `database/migrations/*_create_packages_table.php`
  - `database/migrations/*_create_package_media_table.php`
  - `database/migrations/*_create_client_packages_table.php`
  - `database/migrations/*_create_client_channels_table.php`
  - `database/migrations/*_create_deliveries_table.php`
  - `app/Models/Package.php`
  - `app/Models/ClientPackage.php`
  - `app/Models/ClientChannel.php`
  - `app/Models/Delivery.php`
- **Reference Files:**
  - `app-data/v1-database-design.md §7, §10.7`

---

## 4. Prompt (For the Coding AI)

> Implement distribution migrations per Contract. Use `timestamptz`, varchar+CHECK pgsql-only, jsonb, numeric. Partitioning for deliveries is logical v1 — create as plain table with correct indexes (defer native RANGE partition). Keep `migrate:fresh` green. Create models with fillable/casts/relations.

---

## 5. Test Criteria

- [ ] `php artisan migrate:fresh` green
- [ ] `packages.kind/status`, `client_channels.type`, `deliveries.deliverable_type/status` CHECKs reject invalid
- [ ] `packages.code` unique, `deliveries.idempotency_key` unique, `client_packages` UNIQUE (client_id, package_id, starts_at)
- [ ] FKs cascade/restrict as specced
- [ ] Indexes present (package_media PK, deliveries client+created, partial failed)
- [ ] All timestamps timestamptz (bare=0)
- [ ] `php -l` clean, `php artisan test` green

---

## 6. Completion Notes

- **Shipped:** 5 migrations (packages, package_media, client_packages, client_channels, deliveries) + 4 models (Package, ClientPackage, ClientChannel, Delivery). jsonb, numeric, varchar+CHECK, partial index.
- **Tests:** `migrate:fresh` green (30 migrations). `php artisan test` 25/25 passed. `php -l` clean. Pg checks: kind/status/type/deliverable_type CHECKs OK, uniques (code, idempotency, client+package+starts) OK, indexes (client+created, partial failed) verified.
- **Live Smoke:** N/A (DB-only task)
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
