# UNB Wire — Production Deploy Reference

> Reference configs for the 1,000-concurrent-portal / 50-concurrent-admin load profile (M15-SCALE-002).
> Basis: `docs/research/scale-performance-optimization-research.md`. **Tune per server — don't blind-apply.**
> Formulas + measurement steps are inline in each file.

| Layer | File | Notes |
|---|---|---|
| PgBouncer | `pgbouncer/pgbouncer.ini` | transaction mode; write pool → primary, read pool → replica; PDO caveat below |
| PostgreSQL 16 | `postgresql/postgresql-16-oltp.conf` | 16GB/8vCPU worked example; `pg_stat_statements` + 250ms slow log on |
| PHP-FPM | `php/php-fpm-pool.ini` | static pool sized by measured worker RSS; `pm.max_requests` recycle |
| OPcache | `php/opcache.ini` | `validate_timestamps=0` **requires FPM reload on deploy** |

## Rollout order (cheapest → deepest)

1. OPcache + FPM + `php artisan optimize` on deploy (`config/routes/views/events` cached) — no app change.
2. PgBouncer transaction pools in front of primary + replica; drop PG `max_connections` to ~120.
3. CDN in front of the API/portal origin (policy below).

## PgBouncer + Laravel/PDO — the one caveat

- PgBouncer ≥1.21 tracks **protocol-level** prepared statements (`max_prepared_statements > 0`), but **PHP/PDO only cooperates on PHP 8.4 + libpq 17**. Until then, transaction pooling requires emulated prepares in `config/database.php` (`pgsql`):
  ```php
  'options' => [PDO::ATTR_EMULATE_PREPARES => true],
  ```
  Alternative: run the staff/admin pool in **session** mode (only ~50 admins).
- Transaction pooling breaks session features: `SET/RESET`, `LISTEN`, session advisory locks, temp tables across transactions. Verified: this codebase uses none across transaction boundaries (`search_path` is default `public`).
- **Migrations/DDL and artisan maintenance bypass the pooler** (direct connection).

## CDN edge policy (feed + portal)

- Cache at edge: `GET /api/v1/portal/feed*`, `GET /api/v1/portal/story/*` — origin already sends `Cache-Control: public, max-age=60`; match edge TTL to 60s.
- Never cache: `*/login`, `*/forgot-password`, `*/reset-password`, `*/search-token`, `POST *`.
- Publish visibility is driven by the origin epoch bump (DEC-017) — worst-case client staleness = edge TTL (≤60s), within the publish→feed 60s SLO when edge TTL ≤ origin TTL. Purge-on-publish only if the SLO tightens below 60s.
- Media derivatives already ride immutable versioned URLs — cache forever + `stale-while-revalidate`.

## Alert thresholds (from the research doc §7)

- PgBouncer: `query_wait_timeout` hits, pool saturation (clients waiting > 0 sustained).
- PostgreSQL: `replay_lag > 5s` (feed SLO), connection count approaching `max_connections`, slow-log top queries.
- Redis: `evicted_keys > 0` (policy: keep `volatile-lru` while queue shares the instance — queue keys carry no TTL), queue depth per Horizon supervisor.
