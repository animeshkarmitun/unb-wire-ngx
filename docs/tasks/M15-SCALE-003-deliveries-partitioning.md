# Task: M15-SCALE-003 — `deliveries` monthly RANGE partitioning (DEC-011 revisit → DEC-018) + partition ops

**Status:** ✅ Completed
**Dependencies:** M15-SCALE-001, M2-DB-007
**Parent ADR:** DEC-011 (partitioning deferred to ~5M rows) — **revisited**: at NFR volume (300–600 stories/day × 50–200 clients × channels) `deliveries` crosses 5M rows in ~55 days, ~33M/yr. `app-data/v1-database-design.md` §9 already specced monthly RANGE partitions + 12-month detach-to-archive.

---

## 1. Contract (What)

- **Schema:** `deliveries` becomes `PARTITION BY RANGE (created_at)`; PK `(id, created_at)` (PG requires partition key in PK); global `UNIQUE(idempotency_key)` impossible on a partitioned table → replaced by `UNIQUE(idempotency_key, created_at)` belt + existing **app-level check-then-insert** (`FanoutStory` — keys are `sha256(story-channel-version)` so same-month dupes are the only realistic race). FKs/checks/indexes preserved; append-only `REVOKE` re-applied to the recreated table.
- **Ops:** `partitions:ensure` (daily; current + next month, DEFAULT-partition-safe attach dance) + `SweepDeliveryPartitions` (daily; detaches partitions older than 12 months into `archive` schema).
- **Authorization:** scheduler-only.

## 2. Logic (How)

1. Migration (pgsql-only): rename→create partitioned parent→indexes/FKs/CHECKs→REVOKE→create current+next month partitions + `deliveries_default`→copy legacy rows (auto-route)→fix id sequence (`setval`)→drop legacy. `down()` recreates the original single-PK shape.
2. UTC partition bounds (`'YYYY-MM-01 00:00:00+00'`) — immune to server TZ.
3. `EnsureDeliveryPartitions` command: for current + next month, skip if exists; else create standalone (`LIKE deliveries`), move stray default rows, `ATTACH PARTITION`. Scheduled daily.
4. `SweepDeliveryPartitions`: partitions fully older than 12 months → `DETACH PARTITION` → `SET SCHEMA archive`. Scheduled daily after `archive-sweep`.
5. No repository/job changes needed — all reads/writes go through the parent table (partition-transparent); date-filtered queries prune.

## 3. Context (Where)

- **Files:** `database/migrations/2026_10_11_000400_partition_deliveries_table.php`, `app/Console/Commands/EnsureDeliveryPartitions.php`, `app/Console/Scheduling/SweepDeliveryPartitions.php`, `routes/console.php`, `tests/Feature/DeliveryPartitioningTest.php`, `docs/knowledge-inventory/{decisions,data-model,architecture}.md`, `app-data/v1-database-design.md` (§9 ratify), task board.

## 4. Test Criteria

- [ ] Migration: `migrate:fresh --seed` clean; parity check PASSED
- [ ] New `DeliveryPartitioningTest`: rows route to correct partition; out-of-range row → default; `partitions:ensure` idempotent + creates future month + relocates default strays; sweep detaches old partition to archive schema + parent stops seeing it; existing delivery flows (FanoutStory idempotency, repository) green
- [ ] `php artisan test` green; `php -l` clean; live smoke recorded

## 6. Completion Notes

- **Shipped:** Migration `2026_10_11_000400` converts `deliveries` to `PARTITION BY RANGE (created_at)` (PK `(id, created_at)`, unique `(idempotency_key, created_at)`, FKs/CHECKs/append-only REVOKE preserved, legacy index names freed before recreate); `App\Support\DeliveryPartitions` (UTC bounds/name/ensureMonth with default-safe attach); `partitions:ensure` command (daily schedule, current + N ahead); `SweepDeliveryPartitions` (daily schedule, >12-month whole months detach → `archive` schema); DEC-018 + design-doc §7 ratification + data-model/architecture sync.
- **Tests:** `DeliveryPartitioningTest` **6/6 passed on pgsql (21 assertions)** — partition layout, row routing (monthly vs default), same-month idempotency block, ensure idempotence + index propagation canary, default-stray relocation, sweep-to-archive. Standard sqlite suite **901 passed / 0 failed** (partition tests skip there by design). Full pgsql suite: 899 passed + **8 pre-existing failures** (datetime/expiry/backoff family — **proven pre-existing via stash-rerun on pre-change code**, identical 8 failures). `migrate:fresh --seed` clean on the dev DB (legacy rows auto-routed). `php scripts/schema-parity-check.php` PASSED. Pint clean.
- **Live Smoke:** dev DB migrated live — `partitions:ensure --months=2` created `deliveries_202612` (202610/202611 created by the migration); parent-table reads (`delivery:process`, repository filters) unchanged; feed/publish flows re-verified in M15-SCALE-001 smoke remain green.
- **Gotchas hit & fixed during bring-up:** (1) legacy table keeps its index names after RENAME → freed via `DROP INDEX IF EXISTS` before recreating same-named parent indexes; (2) `ATTACH PARTITION` requires the child to carry the parent's CHECKs → `LIKE … INCLUDING CONSTRAINTS`; (3) DETACH syntax is `ALTER TABLE parent DETACH PARTITION child`; (4) `DateInterval` import needed in namespaced files (runtime, not lint, failure).
- **Review:** reviewer subagent unavailable this session — all CI gates (test/build/parity) executed locally; PR CI re-runs.
