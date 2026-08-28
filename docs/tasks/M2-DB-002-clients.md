# Task: M2-DB-002 — Clients: clients, client_users, client_api_keys

**Status:** ✅ Completed
**Dependencies:** M2-DB-001
**Parent ADR:** v1-database-design §3 + §10.2

---

## 1. Contract (What)

- **Inputs / Validation:** No API inputs. Schema must match `v1-database-design.md §3` exactly.
- **Outputs / Response:** Migrations green on PostgreSQL 16, models cast correctly, `php artisan migrate:fresh --seed` passes.
- **Authorization:** `client_users.client_role_id` FK `roles` (type='client') — portal views gated by `role_permissions`; `client_api_keys` scoped + rate-limited.

---

## 2. Logic (How)

1. Create `clients` — `id` bigint identity PK, `public_id` ulid unique, `name` varchar(160), `code` varchar(32) unique, `type` varchar(24) CHECK in ('newspaper','tv','online','radio','govt','agency'), `country` char(2) default 'BD', `timezone` varchar(64) default 'Asia/Dhaka', `status` varchar(16) CHECK in ('active','suspended','closed') default 'active', `billing_email` citext null, `notes` text null, `created_at`/`updated_at` timestamptz default now(), `deleted_at` timestamptz null (soft deletes).
2. Create `client_users` — `id` identity PK, `client_id` FK clients cascade, `name` varchar(120), `email` citext unique, `password` varchar, `client_role_id` FK roles restrict, `status` varchar(16) CHECK in ('active','invited','deactivated') default 'active', `last_login_at` timestamptz null, `created_at`/`updated_at` timestamptz default now().
3. Create `client_api_keys` — `id` identity PK, `client_id` FK clients cascade, `name` varchar(80), `key_hash` varchar(128) unique, `scopes` jsonb default '[]', `rate_limit_rpm` int default 60, `last_used_at`/`expires_at`/`revoked_at` timestamptz null, `created_at`/`updated_at` timestamptz default now().
4. Enums via varchar+CHECK (pgsql-only guards for sqlite tests), citext for emails, timestamptz everywhere, ULID public_id.
5. FKs: client_users.client_id cascade, client_users.client_role_id restrict, client_api_keys.client_id cascade.

---

## 3. Context (Where)

- **Files to Create / Modify:**
  - `database/migrations/*_create_clients_table.php`
  - `database/migrations/*_create_client_users_table.php`
  - `database/migrations/*_create_client_api_keys_table.php`
  - `app/Models/Client.php`
  - `app/Models/ClientUser.php`
  - `app/Models/ClientApiKey.php`
  - `app/Models/Role.php` (add clientUsers relation if needed)
- **Reference Files:**
  - `app-data/v1-database-design.md §3, §10.2`
  - `database/migrations/2026_08_27_000011_create_roles_table.php` (pattern for CHECK)
  - `app/Models/User.php` (ULID booted pattern)

---

## 4. Prompt (For the Coding AI)

> Implement clients migrations per Contract. Use `timestamptz` everywhere, varchar+CHECK not pg enums (pgsql-only), citext for billing_email/email, ULID public_id for clients. Match column specs verbatim. Add FKs with correct onDelete. Keep `migrate:fresh` green. Create models with fillable/casts/relations and ULID generation.

---

## 5. Test Criteria

- [ ] `php artisan migrate:fresh` green on PostgreSQL 16
- [ ] `clients.type` CHECK rejects invalid value
- [ ] `clients.status` CHECK rejects invalid value
- [ ] `clients.code` unique, `clients.public_id` ulid unique
- [ ] `clients.billing_email` is citext
- [ ] `clients.deleted_at` soft delete column exists
- [ ] `client_users.email` is citext unique
- [ ] `client_users.status` CHECK enforced
- [ ] `client_users.client_id` FK cascade, `client_role_id` FK restrict
- [ ] `client_api_keys.key_hash` unique
- [ ] `client_api_keys.client_id` FK cascade
- [ ] All timestamp cols are timestamptz (bare timestamp count = 0)
- [ ] `php -l` clean, `php artisan test` green

---

## 6. Completion Notes

- **Shipped:** 3 migrations (clients, client_users, client_api_keys) + 3 models (Client, ClientUser, ClientApiKey). All `timestamptz`, varchar+CHECK (pgsql-only), citext emails, ULID public_id, FKs (cascade for client_id, restrict for client_role_id).
- **Tests:** `migrate:fresh` green (12 migrations). `php artisan test` 25/25 passed. `php -l` clean. Pg checks: clients_type_check/client_status/client_users_status OK, billing_email/email=citext, bare_timestamp=0, FKs cascade/restrict verified.
- **Live Smoke:** N/A (DB-only task, no routes)
- **Review:** —

---

## 7. Prompt Ready?

- [x] Yes
