# Task: M2-DB-001 — Auth base: roles, permissions, users, devices, tokens

**Status:** ✅ Completed
**Dependencies:** M1-BOOT-001
**Parent ADR:** DEC-006, DEC-007, v1-database-design §2 + §10.1

---

## 1. Contract (What)

- **Inputs / Validation:** No API inputs. Schema must match `v1-database-design.md §2` exactly.
- **Outputs / Response:** Migrations green on PostgreSQL 16, models cast correctly, `php artisan migrate:fresh --seed` passes.
- **Authorization:** `RbacService.assertCan` will read `role_permissions` (this task provides the table).

---

## 2. Logic (How)

1. Enable `citext` extension (`CREATE EXTENSION IF NOT EXISTS citext`).
2. Create `roles` — `id` bigint identity PK, `name` varchar(80) unique, `type` varchar(16) CHECK in ('system','custom','client'), `description` text null, `is_locked` bool default false, `created_at`/`updated_at` timestamptz default now().
3. Create `role_permissions` — `id` identity PK, `role_id` FK roles cascade, `module` varchar(40), booleans `can_view/can_create/can_edit/can_publish/can_delete` default false, UNIQUE (role_id, module).
4. Alter `users` (staff) — add `public_id` ulid unique, `role_id` FK roles restrict null, `desk` varchar(40) null, `timezone` varchar(64) default 'Asia/Dhaka', `status` varchar(16) CHECK in ('active','invited','deactivated') default 'active', `last_seen_at` timestamptz null, `deleted_at` timestamptz null (soft deletes). Convert `email` to citext unique, ensure all timestamps are timestamptz.
5. Create `devices` — `id` identity PK, `user_id` FK users cascade, `label` varchar(120), `platform` varchar(16) CHECK in ('ios','android','web'), `app_version` varchar(20) null, `last_seen_at`/`revoked_at` timestamptz null, `created_at`/`updated_at` timestamptz.
6. Ensure `personal_access_tokens` (Sanctum) exists with `device_id` FK devices null column added.
7. Ensure `failed_jobs` table exists (from jobs migration) — create if missing.
8. Transaction boundaries: each migration in single transaction. Append-only not needed here.

---

## 3. Context (Where)

- **Files to Create / Modify:**
  - `database/migrations/*_create_roles_table.php`
  - `database/migrations/*_create_role_permissions_table.php`
  - `database/migrations/*_alter_users_table_add_auth_fields.php`
  - `database/migrations/*_create_devices_table.php`
  - `database/migrations/*_add_device_id_to_personal_access_tokens.php`
  - `app/Models/Role.php`
  - `app/Models/RolePermission.php`
  - `app/Models/Device.php`
  - `app/Models/User.php` (update casts, fillable, relations)
- **Reference Files:**
  - `app-data/v1-database-design.md §2, §10.1`
  - `docs/knowledge-inventory/decisions.md DEC-006/007`
  - `database/migrations/0001_01_01_000000_create_users_table.php` (existing)

---

## 4. Prompt (For the Coding AI)

> Implement auth base migrations per Contract. Use `timestamptz` everywhere, varchar+CHECK not pg enums, ULID public_id. Enable citext. Match column specs verbatim. Add FKs with correct onDelete (cascade for role_permissions/devices, restrict for users.role_id). Ensure sanctum personal_access_tokens device_id. Keep `migrate:fresh` green. Update models with relations/casts.

---

## 5. Test Criteria

- [ ] `php artisan migrate:fresh` green on PostgreSQL 16
- [ ] `SELECT * FROM pg_extension WHERE extname='citext'` returns 1 row
- [ ] `roles.type` CHECK rejects invalid value
- [ ] `users.email` is citext unique, `users.public_id` ulid unique
- [ ] `users.status` CHECK rejects invalid
- [ ] `role_permissions` UNIQUE (role_id, module) enforced
- [ ] `devices.platform` CHECK enforced
- [ ] All timestamp cols are timestamptz (grep `information_schema` for `timestamp without time zone` = 0 on these tables)
- [ ] `php -l` clean, `php artisan test` green
- [ ] Seeder can insert system roles (is_locked=true) without violation

---

## 6. Completion Notes

- **Shipped:** 6 migrations (citext extension, roles, role_permissions, users alter, devices, personal_access_tokens with device_id) + 3 models (Role, RolePermission, Device) + User updated (SoftDeletes, public_id ULID auto-generation, role/desk/timezone/status/last_seen_at, citext email). All `timestamptz`, varchar+CHECK (pgsql-only guards for sqlite tests), ULID public_id, FKs (cascade for permissions/devices, restrict for users.role_id). Sanctum tokens include device_id FK.
- **Tests:** `migrate:fresh --seed` green on PostgreSQL 16 (7-8 tables), sqlite-guarded for test suite. `php artisan test` 25/25 passed (fixed ProfileTest for SoftDeletes, UserFactory public_id, User booted ULID). `pint --test` passed, `php -l` clean.
- **Live Smoke:** `config:clear`/`route:clear`/`view:clear` cleared. `GET /admin` authenticated 200. Postgres checks: citext=1, roles_type_check=1, users_status_check=1, email=citext, timestamps=timestamptz, bare_timestamp_cols=0.
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
