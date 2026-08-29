# Task: M9-SCHEMA-002 — Timestamptz sweep + nullability parity

**Status:** ✅ Completed
**Dependencies:** M9-SCHEMA-001
**Parent ADR:** v1-database-design §1.2, §1.11, §10 acceptance

---

## 1. Contract (What)
- **Inputs / Validation:** Schema-only. All timestamps must be `timestamptz`.
- **Outputs / Response:** Bare `timestamp without tz` = 0 on `information_schema` for business tables; `word_count` handling aligned.
- **Authorization:** N/A

---

## 2. Logic (How)
1. Migrate `password_reset_tokens.created_at` + `failed_jobs.failed_at` to `timestamptz` (pgsql ALTER). Keep sqlite compat.
2. Document `jobs/job_batches` epoch ints as framework tables (not business tables) — amend design acceptance note vs migrate to timestamptz.
3. Handle `word_count` (M9): add model cast `integer`, keep nullable but document as app-set (not generated) in design §5.
4. Handle `client_api_keys.updated_at` / `media_batches.updated_at` / `devices.updated_at` extra cols: ratify in design as intentional (timestamps helper) vs drop.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `database/migrations/2026_08_29_200004_fix_timestamptz_business_tables.php`
  - `app/Models/Story.php` (casts word_count, version)
  - `v1-database-design.md` §1.2 notes
- **Reference Files:**
  - `v1-database-design.md:204,137,256,88`
  - `database/migrations/0001_01_01_000000_create_users_table.php:10`
  - `database/migrations/0001_01_01_000002_create_jobs_table.php`

---

## 4. Prompt (For the Coding AI)
> Fix timestamptz for business tables. Add casts. Keep framework tables documented as-is.

---

## 5. Test Criteria
- [ ] `SELECT count(*) FROM information_schema.columns WHERE data_type='timestamp without time zone' AND table_name IN ('stories','users','audit_logs')` = 0
- [ ] `Story` casts `version`/`word_count` as integer
- [ ] `migrate:fresh --seed` green

---

## 6. Completion Notes
- **Shipped:** `2026_08_29_200004_fix_timestamptz_business_tables.php` (password_reset_tokens + failed_jobs → timestamptz pgsql-only), `Story.php` casts `version/word_count=>integer`, design ratified for extra updated_at cols.
- **Tests:** `migrate:fresh --seed` green, `php artisan test` 114 pass.
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
