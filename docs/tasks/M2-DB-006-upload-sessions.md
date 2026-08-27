# Task: M2-DB-006 — Field ops: upload_sessions (assignments deferred DEC-008)

**Status:** ✅ Completed
**Dependencies:** M2-DB-005
**Parent ADR:** v1-database-design §6 + §10.6, DEC-008, data-model.md §3

---

## 1. Contract (What)

- **Inputs / Validation:** No API inputs. Schema must match `v1-database-design.md §6 upload_sessions` exactly. `assignments` deferred per DEC-008 — not created here.
- **Outputs / Response:** Migrations green on PostgreSQL 16, model cast correctly, `php artisan migrate:fresh --seed` passes.
- **Authorization:** N/A (session tied to `user_id`).

---

## 2. Logic (How)

1. Create `upload_sessions` — `id` uuid PK (= tus upload id), `user_id` FK users cascade, `kind` varchar(8) CHECK photo/video, `filename` varchar(240), `size_bytes` bigint, `offset_bytes` bigint default 0, `status` varchar(12) default active CHECK active/completed/aborted/expired, `meta` jsonb null, `expires_at` timestamptz, `created_at`/`updated_at` timestamptz default now().
2. Use `timestamptz` everywhere, varchar+CHECK pgsql-only, uuid PK (gen_random_uuid() not needed — app supplies tus id).

---

## 3. Context (Where)

- **Files to Create / Modify:**
  - `database/migrations/*_create_upload_sessions_table.php`
  - `app/Models/UploadSession.php`
- **Reference Files:**
  - `app-data/v1-database-design.md §6, §10.6`

---

## 4. Prompt (For the Coding AI)

> Implement upload_sessions migration per Contract. Use `uuid` PK, `timestamptz`, varchar+CHECK pgsql-only, jsonb. Match specs verbatim. Keep `migrate:fresh` green. Create model with casts/relations (incrementing false, keyType string).

---

## 5. Test Criteria

- [ ] `php artisan migrate:fresh` green
- [ ] `upload_sessions.kind` CHECK rejects invalid
- [ ] `upload_sessions.status` CHECK rejects invalid
- [ ] `id` is uuid PK, `user_id` FK cascade
- [ ] All timestamps timestamptz (bare=0)
- [ ] `php -l` clean, `php artisan test` green

---

## 6. Completion Notes

- **Shipped:** 1 migration (upload_sessions) + 1 model (UploadSession). UUID PK, varchar+CHECK, timestamptz, jsonb.
- **Tests:** `migrate:fresh` green (25 migrations). `php artisan test` 25/25 passed. `php -l` clean. Pg checks: kind/status CHECKs OK, FK cascade verified, bare_timestamp=0.
- **Live Smoke:** N/A (DB-only task)
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
