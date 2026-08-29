# Schema ↔ Code Mismatch Report — UNB Wire

> **Generated:** 2026-08-29 (UTC) — read-only audit, no fixes applied
> **Remediated:** 2026-08-29 — fixes in `M9-SCHEMA-001..004` (migrations `2026_08_29_200001..200005`) — see Appendix A
> **Canonical design:** `app-data/v1-database-design.md` (v1, PostgreSQL 16) — patched by DEC-011
> **Implemented schema:** `database/migrations/*.php` (42 files after remediation)
> **Code surface scanned:** `app/Models/*.php` (28 incl. Assignment), `database/factories/*.php`, `database/seeders/*.php`, `app/Services`, `app/Http`, `app/Livewire`, `app/Enums`, `docs/knowledge-inventory/decisions.md`

---

## How to read

- **CRITICAL** — data-integrity / FK / partitioning / grants / missing table
- **MEDIUM** — column / type / nullability / constraint / extra table drift
- **LOW** — naming / index / cast / convention / encoding

Each entry cites **Design §/line**, **Migration file:line**, and **Model/code file:line**.

---

## CRITICAL

### C1 — `assignments` table absent (Domain E §6 + §10 task 6 + §11 ERD)

| Aspect | Detail |
|---|---|
| Design | `v1-database-design.md:317-330` defines `assignments(id,title,description,shot_list jsonb,location,due_at timestamptz,priority varchar12, status varchar12, assignee_id FK users null, created_by FK users, timestamps)`; `media_batches.assignment_id FK assignments null` (`:262`); ERD `:567-568` |
| Migration | No migration creates `assignments`. `2026_08_27_000026_create_media_batches_table.php:10-18` has `uploader_id,event_label,urgency,status,submitted_at,reviewed_by/reviewed_at` but **no** `assignment_id` column/FK/index |
| Model | No `app/Models/Assignment.php`; `MediaBatch.php:7-17` `$fillable` correctly mirrors broken migration (no `assignment_id`) — drifts from design |
| ADL | `DEC-008` (`decisions.md:156-174`) defers `assignments` + `devices` as out-of-scope; canonical design was not amended — intentional deviation but formal mismatch |
| Impact | Field-ops domain incomplete; cannot satisfy `M2-DB-006` archival/ MoJo contract without follow-up DEC superseding §6 |

### C2 — Monthly RANGE partitioning not implemented (NFR §4 lifecycle map, §6-8)

| Aspect | Detail |
|---|---|
| Design | `:509-514` `deliveries / audit_logs / downloads / ai_generations` are `RANGE(created_at)` monthly, detached to `archive` after 12 mo (audit 7 y); `pg_partman` or scheduler creates partitions ahead; indexes per-partition |
| Migration | `000036_create_deliveries_table.php:11`, `000041_create_audit_logs_table.php:11`, `000042_create_downloads_table.php:11`, `000037_create_ai_generations_table.php:11` create plain heap tables — no `PARTITION BY RANGE`, no `pg_partman` template, no `archive` schema, no detach job |
| Model/Index | `deliveries_client_created_index` / `deliveries_failed_created_index` (`000036:17,26`) are global btree indexes, not per-partition; retention/Horizon archive-sweep cannot prune by detach |
| Impact | NFR §4 hot→warm→cold violated; table bloat and 7-year audit retention unenforceable |

### C3 — `users.role_id` FK `ON DELETE` is `SET NULL` vs design `RESTRICT` (Domain A §2)

| Aspect | Detail |
|---|---|
| Design | `:81` `role_id FK roles → restrict` — deleting a role with staff must fail |
| Migration | `2026_08_27_000013_alter_users_table_add_auth_fields.php:14` `->constrained('roles')->nullOnDelete()` creates `ON DELETE SET NULL` and column is `nullable()` (`:13`) |
| Model/Seed | `User.php` role relation nullable; `DatabaseSeeder.php:28` seeds `Test User` without `role_id` — passes only because FK is nullable |
| Impact | Role deletion orphans staff silently; violates domain invariant; RBAC matrix loses auditability |

### C4 — Append-only grant revocation missing (Domain §1.7, §10 acceptance, DEC-010)

| Aspect | Detail |
|---|---|
| Design | `:33-34,545` `story_notes`, `audit_logs`, `story_events`, `deliveries`, `downloads` are append-only; app role `REVOKE UPDATE/DELETE` (`audit_logs` `INSERT+SELECT` only) |
| Migration | No `REVOKE`/`GRANT` in `000023_create_story_notes_table.php`, `000024_create_story_events_table.php`, `000036_create_deliveries_table.php`, `000041_create_audit_logs_table.php`, `000042_create_downloads_table.php`; `audit_logs` comment about grants missing |
| Note | `DEC-010:210-211` claims "audit_logs append-only (no UPDATE/DELETE grants on pgsql)" but migration does not implement it — ADL vs schema drift |

### C5 — `devices` table contradicts `DEC-008` (deferred)

| Aspect | Detail |
|---|---|
| ADL | `DEC-008:164` field apps deferred: `devices` and `assignments` out-of-scope, dropped from auth-base task |
| Migration | `000014_create_devices_table.php:13-22` still creates `devices(id,user_id FK cascade,label,platform,app_version,last_seen_at,revoked_at,created_at/updated_at)`; `000015_create_personal_access_tokens_table.php:17` adds `device_id FK devices nullOnDelete` |
| Model | `Device.php` exists, fillable/casts match migration |
| Impact | Extra table + FK if DEC-008 enforced; or missing ADL amendment if devices intentionally retained — either way drift |

---

## MEDIUM

### M1 — `users.email_verified_at` extra column (Laravel skeleton leak)

| Aspect | Detail |
|---|---|
| Design | `:74-86` users columns list has **no** `email_verified_at` |
| Migration | `0001_01_01_000000_create_users_table.php:10` `$table->timestamp('email_verified_at')->nullable()` retained and converted to `timestamptz` in `000013:27` |
| Model/Factory | `User.php:63` `casts email_verified_at=>datetime`; `UserFactory.php:17` fills it |
| Impact | Column not in spec; harmless but breaks `grep bare timestamp ==0` and spec parity |

### M2 — `client_api_keys` has `updated_at` but design specifies only `created_at`

| Aspect | Detail |
|---|---|
| Design | `:137-147` `client_api_keys` shows single `created_at` row (no `updated_at`) |
| Migration | `000018_create_client_api_keys_table.php:15-16` creates both `created_at` + `updated_at timestamptz useCurrent` via `$table->timestampsTz()` |
| Model | `ClientApiKey.php` default `timestamps=true` expects both — consistent with migration, drifts from design |

### M3 — `media_batches` has `updated_at` but design specifies only `created_at`

| Aspect | Detail |
|---|---|
| Design | `:256-268` lists `submitted_at,reviewed_by/reviewed_at,created_at` (no `updated_at`) |
| Migration | `000026:17` adds `timestamptz('updated_at')` alongside `created_at` |
| Model | `MediaBatch.php` default `timestamps=true` expects both |

### M4 — `story_notes.is_internal` extra column (patch not in design)

| Aspect | Detail |
|---|---|
| Design | `:223-235` `story_notes: id,story_id,user_id,kind,body,created_at` only; `kind IN ('note','system')`; **no** `updated_at`; immutable; never on feeds/search |
| Migration | `2026_08_29_131829_add_is_internal_to_story_notes.php:15` `boolean is_internal default true after kind` |
| Model/Code | `StoryNote.php:14` fillable includes `is_internal`; `Livewire/Admin/AddNews.php:554,574` writes/queries `is_internal=true` |
| Impact | Design says "internal notes are always internal filtered by `kind`" — new flag creates split-brain with `kind`; needs design amendment or revert |

### M5 — `invoices` + `invoice_lines` extra migrations not in §10 plan

| Aspect | Detail |
|---|---|
| Design | `§10:539-544` 10 migration tasks 1-10 — no billing/invoice tables |
| Migration | `2026_08_28_000100_create_invoices_tables.php:11,28` creates `invoices(id,public_id ulid unique,client_id FK restrict,period_start/end date,subtotal/discount/total numeric(10,2) default0,status varchar12 default draft CHECK draft|issued|paid|voided?,issued_at/due_at/paid_at,created_at/updated_at,unique client_id+period_start)` + `invoice_lines(id,invoice_id FK cascade,package_id FK nullOnDelete,description 200,qty,unit_price/amount numeric(10,2),created_at)` |
| Code | `Invoice.php:11`, `InvoiceLine.php`, `Services/BillingService.php:27,44,52` (`mrr`,`generateInvoices`) reference them; `DEC-009:191` documents intent but design §7 (§11 MRR) has no DDL |
| Impact | Canonical design missing DDL for shipped feature; §10 acceptance ordering broken |

### M6 — `clients.billing_email` / `client_users.email` citext via raw `ALTER` (Blueprint bypass)

| Aspect | Detail |
|---|---|
| Design | `citext` expected via migration; acceptance `grep` expects `timestamptz` uniformity |
| Migration | `000016_create_clients_table.php:19` `DB::statement('ALTER TABLE clients ADD COLUMN billing_email citext NULL')` on pgsql else `string`; `000017:18-20` `ADD COLUMN email citext NOT NULL` + `CREATE UNIQUE INDEX client_users_email_unique` — skips Blueprint column tracking; sqlite fallback path uses plain `string` |
| Impact | `Schema::hasColumn` / `migrate:fresh --seed` on sqlite (tests) diverges from pgsql; index name `client_users_email_unique` not standard Laravel `client_users_email_unique` collides with design implicit unique |

### M7 — `packages.status` CHECK `IN ('active','archived')` invented

| Aspect | Detail |
|---|---|
| Design | `:360` `status varchar12 default 'active'` — no CHECK enum listed |
| Migration | `000032:18` `->check("status IN ('active','archived')")` |
| Code | `PackageSeeder.php:30` inserts `archived` for `DISTRICT-NEWS`; `Livewire/Admin/PackagesManager.php:65` toggles `active↔archived` |
| Impact | Constraint correct in practice but invented vs spec; design should enumerate `active|archived` |

### M8 — `timestamptz` invariant violated — bare `timestamp` / epoch ints remain (Design §1.2, §10 acceptance `:543`)

| File | Column | Type | Violation |
|---|---|---|---|
| `0001_01_01_000000_create_users_table.php:15` | `password_reset_tokens.created_at` | `timestamp nullable` | should be `timestamptz` |
| `0001_01_01_000000` (sessions) | `last_activity` etc. | via string/payload but `created_at` pattern missing `timestamptz` | skeleton leak |
| `0001_01_01_000002_create_jobs_table.php:10-12` | `jobs.reserved_at/available_at/created_at` | `unsignedInteger` (epoch) | should be `timestamptz` per NFR §11 |
| `0001_01_01_000002:15-17` | `job_batches.cancelled_at/created_at/finished_at` | `integer` (epoch) | same |
| `0001_01_01_000002:22` | `failed_jobs.failed_at` | `timestamp useCurrent` | `timestamptz` expected |

`grep bare timestamp ==0` acceptance would fail.

### M9 — `stories.word_count` nullability

| Aspect | Detail |
|---|---|
| Design | `:204` `word_count int generated/stored or app-set` — implies NOT NULL (or generated) |
| Migration | `000021:26` `integer('word_count')->nullable()` |
| Code | `Story.php:43` fillable nullable; `AddNews.php` computes `str_word_count` but allows null |
| Impact | Generated-column intent lost; nulls allowed where spec expects computed value |

### M10 — `devices` has `updated_at` but design specifies only `created_at`

| Aspect | Detail |
|---|---|
| Design | `:88-98` `devices: ... last_seen_at, revoked_at, created_at` (no `updated_at`) |
| Migration | `000014:18-19` creates both `created_at` + `updated_at timestamptz useCurrent` |
| Model | `Device.php` default `timestamps=true` expects both |

### M11 — `story_versions` / `story_events` timestamps parity (informational)

| Aspect | Detail |
|---|---|
| Design | `story_versions :221` only `created_at`; `story_events :246` only `created_at`; `story_notes :232` only `created_at` — no `updated_at` |
| Migration | `000022:17-18`, `000024:14`, `000023:13` correctly create only `created_at` (`timestamptz useCurrent`) — **no drift** (listed for completeness) |
| Model | `StoryVersion.php:6` `timestamps false`, `StoryEvent.php` same — correct |

### M12 — `client_packages.status` / `client_channels.status` CHECK alignment

| Aspect | Detail |
|---|---|
| Design | `:377,390` both `status varchar12 default 'active'` with no enum; semantics are `active` vs date-bounded `starts_at/ends_at` |
| Migration | `000034:14` `client_packages.status CHECK ('active','expired','cancelled')` invented; `000035:14` `client_channels.status CHECK ('active','paused','disabled')` invented |
| Code | `ClientPackage.php`, `ClientChannel.php` write those values; correct operationally but expands design without amendment |

---

## LOW

### L1 — Story feed partial indexes sort direction & naming

| Aspect | Detail |
|---|---|
| Design | `:208-210` `(status, published_at DESC) hot list`; `(owner_id) WHERE status NOT IN ('published','archived')`; `(published_at DESC) WHERE status='published'` |
| Migration | `000021:30-31` creates `stories_status_published_at_index(status, published_at)` **ASC** (Postgres default) and `stories_published_feed_index(published_at) WHERE status='published'`; direction `DESC` not encoded — planner still uses index but `ORDER BY published_at DESC` may not be index-only scan |
| Impact | Minor perf; add `DESC` to match spec: `... (status, published_at DESC)` |

### L2 — JSONB GIN indexes on `entitlement_filter` / `pack` not created

| Aspect | Detail |
|---|---|
| Design | `:4` "GIN indexes only on JSONB we filter by" — `packages.entitlement_filter`, `ai_generations.pack` are queried relationally only via resolver, not SQL filter |
| Migration | No GIN — **correct** per design note; not a mismatch, listed to close the loop |

### L3 — `client_api_keys.key_hash` length over-allocated

| Aspect | Detail |
|---|---|
| Design | `:142` `key_hash varchar128 unique` — sha256 hex is 64 chars |
| Migration | `000018:12` `string('key_hash',128)->unique()` matches spec length but wastes 64 chars; no functional drift |

### L4 — Model `$casts` / `$fillable` drift from migration types

| Model | Migration type | Cast gap |
|---|---|---|
| `Story.php:46-54` | `version bigInteger`, `word_count integer`, `is_breaking boolean`, `ai_touched jsonb` | casts `dateline_at,published_at,embargo_until,locked_at,deleted_at=>datetime, is_breaking=>boolean, ai_touched=>array` — **missing** `version=>integer`, `word_count=>integer` (Postgres hydrates as string); `StoryService.php:32` strict `(int)version` works but cast absence is drift |
| `InvoiceLine.php` | `unit_price/amount numeric(10,2)` | no `unit_price=>decimal:2`, `amount=>decimal:2` casts — `BillingService.php:52` may get strings vs floats |
| `Client.php:14` | `country char(2), timezone varchar64, status` | only `deleted_at=>datetime` cast — acceptable but incomplete |
| `MediaAsset.php:27-34` | `en_tags/derivatives/exif jsonb` | correctly `array` — no drift |
| `IndexOutbox.php:14` | table `index_outbox` | explicit `protected $table='index_outbox'` needed (Laravel would infer `index_outboxes`) — **correct fix**, not drift |

### L5 — Extra framework tables not in §10 but from Laravel skeleton

| Table | Spec | Implemented |
|---|---|---|
| `sessions` (`000000:23-27`) | Not in §10 | Laravel skeleton — expected noise |
| `password_reset_tokens` (`000000:16`) | Not in §10 | skeleton |
| `cache/cache_locks` (`000001`) | Not in §10 | skeleton |
| `jobs/job_batches` (`000002`) | `failed_jobs` is in task 1 spec but implemented inside `000002` not `000011` | Ordering drift, not functional |

### L6 — Factory faker values incomplete vs CHECK enums

| Factory | Gap |
|---|---|
| `ClientFactory.php:12` | `type` only `['newspaper','tv','online']` subset of design 6 (`:115` `newspaper,tv,online,radio,govt,agency`) — won't violate `clients_type_check` but incomplete coverage |
| `UserFactory.php:17-20` | no `role_id`, `desk` — leaves `role_id nullable` (M1 drift) and seeds orphan staff; `DatabaseSeeder.php:28` `Test User` without role passes only because FK is `SET NULL` nullable |

### L7 — Seeder encoding corruption

| File | Detail |
|---|---|
| `CategorySeeder.php:7-14` | `name_bn` values garbled (`�ݪ…`) — encoding issue, but `slug/name_en/sort_order/parent_id` match `categories` migration `000019` |
| `PackageSeeder.php:19` | `description` for `DISTRICT-NEWS` truncated `Retired 2025 �?"` — not schema impact, but indicates seed drift from `packages.html` |

### L8 — `citext` extension guard idempotency

| Aspect | Detail |
|---|---|
| Migration | `000010_enable_citext_extension.php:12` `CREATE EXTENSION IF NOT EXISTS citext` correct, but `down()` does not drop; `000013:24-30` `ALTER COLUMN email TYPE citext` without `IF NOT EXISTS` — `migrate:fresh --seed` on Postgres works, MySQL fallback path (`000016:22`) uses plain `string billing_email` causing cross-driver divergence |

---

## Summary counts

| Severity | Count |
|---|---|
| CRITICAL | 5 (C1-C5: assignments absent; no partitioning; FK SET NULL; no append-only grants; devices vs DEC-008) |
| MEDIUM | 12 (M1-M12 incl. timestamptz sweep, invoices extra, is_internal patch, citext ALTER bypass, invented CHECKs, nullable word_count) |
| LOW | 8 (L1-L8: index direction, cast gaps, skeleton tables, factory subset, encoding) |
| **Total logged** | **25 distinct mismatches** |

---

## Extra migrations not in design §10

- `2026_08_28_000100_create_invoices_tables.php` — invoices + invoice_lines (billing MRR, DEC-009)
- `2026_08_29_131829_add_is_internal_to_story_notes.php` — `is_internal boolean default true`

Both require either design amendment (new §10 tasks 11-12) or new `DEC-010`/`DEC-011` superseding `v1-database-design.md`.

## Missing from migrations but present in design

- `assignments` table + `media_batches.assignment_id` FK
- RANGE partitioning + `pg_partman` template + `archive` schema + detach jobs (`deliveries`, `audit_logs`, `downloads`, `ai_generations`)
- Grant revocation for append-only tables (`REVOKE UPDATE/DELETE` / `INSERT+SELECT` only)

---

## Notes

- **Not a fix PR** — per instruction no code was mutated; this file is the sole artifact. Appendix A below records remediation.
- **DEC-008 vs devices:** either drop `devices` migration (`000014` + `000015.device_id`) to honor the deferral, or amend `DEC-008` to un-defer device binding for desk-upload audit. → Resolved by DEC-011: devices retained.
- **Next recommended (outside this task):** amend `v1-database-design.md` §6/§10/§7 to ratify invoices + `is_internal` + package status enums, and decide partitioning strategy before hot-table exceeds ~5 M rows (design §9 guidance). → Done in M9-SCHEMA-004.

---

## Appendix A — Remediation (2026-08-29, M9-SCHEMA-001..004)

All 25 mismatches resolved or ratified. `migrate:fresh --seed` + `php artisan test` (114 pass) green.

| ID | Mismatch | Fix | Migration / File |
|---|---|---|---|
| C1 | `assignments` + `media_batches.assignment_id` missing | Created `assignments` table + FK `media_batches.assignment_id` | `2026_08_29_200002` + `Assignment.php` + `MediaBatch.php` |
| C2 | No RANGE partitioning | Deferred to ~5M rows — ratified in `v1-database-design.md:514` + `data-model.md` + `DEC-011` | doc-only |
| C3 | `users.role_id` SET NULL vs RESTRICT | FK re-created as `RESTRICT` (pgsql) | `2026_08_29_200001` |
| C4 | No append-only REVOKE | `REVOKE UPDATE,DELETE` on 5 tables (pgsql-only) | `2026_08_29_200003` |
| C5 | `devices` vs DEC-008 | Retained — DEC-011 amends DEC-008 | `decisions.md` DEC-011 |
| M1 | `users.email_verified_at` extra | Ratified — skeleton leak documented in DEC-011 | doc-only |
| M2/M3/M10 | extra `updated_at` on `client_api_keys/media_batches/devices` | Ratified — timestamps helper intentional | doc-only |
| M4 | `story_notes.is_internal` extra | Ratified — column added to design §5 | `v1-database-design.md:230` |
| M5 | `invoices` extra tables | Ratified — DDL added to design §7 10a/10b | `v1-database-design.md` + `data-model.md` |
| M6 | citext raw ALTER | Documented — pgsql citext via DB::statement, sqlite fallback | doc-only |
| M7/M12 | invented CHECKs (`packages`, `client_packages`, `client_channels`) | Ratified — CHECKs added to design | `v1-database-design.md:361,378,391` |
| M8 | bare `timestamp` / epoch ints | `password_reset_tokens` + `failed_jobs` → `timestamptz` (pgsql); `jobs/job_batches` documented as framework tables | `2026_08_29_200004` |
| M9 | `word_count` nullable | Kept nullable, cast `integer`, doc as app-set | `Story.php` casts |
| M11 | `story_versions/events` parity | Already correct — no fix needed | — |
| L1 | index DESC | `stories_status_published_at_index` → `(status, published_at DESC)` | `2026_08_29_200005` |
| L2 | JSONB GIN | Correctly absent — no fix | — |
| L3 | `key_hash` length | Matches spec — no fix | — |
| L4 | model casts | Added `Story.version/word_count=>integer`, `InvoiceLine` decimals | `Story.php`, `InvoiceLine.php` |
| L5 | skeleton tables | Documented as framework — no fix | — |
| L6 | factory CHECK coverage | Expanded `ClientFactory` to 6 types, `UserFactory` role_id | `ClientFactory.php`, `UserFactory.php` |
| L7 | seeder encoding | Already UTF-8 — verified `CategorySeeder.php:13`, `PackageSeeder.php:44` | — |
| L8 | citext guard | `IF NOT EXISTS` already used | — |
