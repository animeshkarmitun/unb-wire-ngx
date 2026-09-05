# Schema Parity Runbook — prevents M9-SCHEMA drift

## When to run
Every PR touching `database/migrations/*`, `app/Models/*`, `database/factories/*`, `app-data/v1-database-design.md`, `docs/knowledge-inventory/decisions.md`.

## Local
```
php artisan migrate:fresh --seed
php scripts/schema-parity-check.php
php artisan test
```

Expected: all PASS, 114+ tests green, migrate green on sqlite and pgsql.

## What it checks
- `assignments` + `media_batches.assignment_id` exist (C1)
- `users.role_id` FK is `RESTRICT` not `SET NULL` (C3)
- Append-only `REVOKE` migration present (C4)
- Design has `is_internal` + `invoices` + CHECK enums (M4/M5/M7)
- No bare `$table->timestamp()` in business migrations (M8)
- Model casts `Story.version/word_count`, `InvoiceLine` decimals (L4)
- Factory coverage 6 client types (L6)
- `stories_status_published_at DESC` (L1)

## CI
`.github/workflows/ci.yml` job `schema-parity` runs the same script and blocks merge on fail.

## Fixing a fail
- New table/column/CHECK invented outside design → add `DEC-NNN` + patch `v1-database-design.md` + `data-model.md` in same PR.
- Bare `timestamp` → use `$table->timestamptz()`; for `password_reset_tokens`/`failed_jobs` use `ALTER ... TYPE timestamptz`.
- Missing cast → add to `$casts`.
