# Task: M15-SCALE-002 — Production deploy reference configs: PgBouncer + PG16 GUCs + PHP-FPM/OPcache + CDN policy

**Status:** ✅ Completed
**Dependencies:** M15-SCALE-001
**Parent ADR:** `docs/research/scale-performance-optimization-research.md` §4.1–4.5 (P0-2/P0-3/P0-4)

---

## 1. Contract (What)

- **Outputs:** `deploy/` reference configs (not app code): `deploy/pgbouncer/pgbouncer.ini`, `deploy/postgresql/postgresql-16-oltp.conf` (16GB worked example + formulas), `deploy/php/php-fpm-pool.ini` + `opcache.ini`, `deploy/README.md` (wiring + **CDN edge policy** for `/api/v1/portal/*` + purge/epoch interplay + rollout checklist).
- **Authorization:** n/a (no runtime code).

## 2. Logic (How)

1. Copy the researched values into deployable reference files with per-server formulas (pool size `(vCPU×2)+1`, FPM `max_children = availRAM / measured worker RSS`).
2. PgBouncer: transaction mode, two pools (write→primary, read→replica), `max_prepared_statements`, PDO caveat (emulated prepares until PHP 8.4+libpq 17), DDL/migrations bypass rule.
3. CDN policy: cache `GET /api/v1/portal/feed|story/*` at edge (60s matching origin `max-age=60`); never cache authed endpoints; publish visibility driven by epoch bump (origin), edge TTL bounded at 60s.

## 3. Context (Where)

- **Files to create:** `deploy/README.md`, `deploy/pgbouncer/pgbouncer.ini`, `deploy/postgresql/postgresql-16-oltp.conf`, `deploy/php/php-fpm-pool.ini`, `deploy/php/opcache.ini`
- **Docs to touch:** architecture.md (deploy section pointer)

## 4. Test Criteria

- [ ] `php -l` n/a (no PHP) — configs validated as reference-only, README documents "tune per server, don't blind-apply"
- [ ] No app behavior change; `php artisan test` stays green (untouched app code)

## 6. Completion Notes

- **Shipped:** `deploy/` — `README.md` (rollout order, PgBouncer+PDO caveat incl. `PDO::ATTR_EMULATE_PREPARES`, CDN edge policy + epoch interplay, alert thresholds), `pgbouncer/pgbouncer.ini` (transaction pools, write/read split, `max_prepared_statements`), `postgresql/postgresql-16-oltp.conf` (16GB/8vCPU worked example + formulas, `pg_stat_statements`, 250ms slow log), `php/php-fpm-pool.ini` (static pool by measured RSS, `pm.max_requests`), `php/opcache.ini` (`validate_timestamps=0` + reload-on-deploy rule).
- **Tests:** No app behavior change — standard suite stays green (901 passed). Reference-only configs; applied at server deploy time per `deploy/README.md` (not part of CI).
- **Live Smoke:** n/a (infra reference; application smoke unchanged from M15-SCALE-001).
- **Review:** CI gates on the PR; reviewer subagent unavailable this session.
