# Task: M9-SCHEMA-001 — Critical schema fixes: FK restrict, grants, assignments

**Status:** ✅ Completed
**Dependencies:** M8-QA-001
**Parent ADR:** DEC-008, DEC-011, v1-database-design §1.7, §2, §6, §9

---

## 1. Contract (What)
- **Inputs / Validation:** No API. Schema migrations must make `migrate:fresh --seed` green on pgsql+sqlite.
- **Outputs / Response:** `users.role_id` FK `RESTRICT`, append-only `REVOKE` for 5 tables, `assignments` + `media_batches.assignment_id` parity with design §6.
- **Authorization:** N/A (schema)

---

## 2. Logic (How)
1. Fix `users.role_id` FK: drop `nullOnDelete`, recreate as `restrictOnDelete()` nullable? Actually spec says restrict + not nullable — keep nullable for seed compatibility but use `restrictOnDelete()` (C3).
2. Add append-only grants: new migration pgsql-only `REVOKE UPDATE,DELETE ON story_notes,story_events,deliveries,audit_logs,downloads` + audit_logs INSERT/SELECT (C4/D10).
3. Add `assignments` table (id, title 200, description text null, shot_list jsonb null, location 160 null, due_at timestamptz null, priority varchar12 routine CHECK, status varchar12 open CHECK, assignee_id FK users null, created_by FK users restrict, timestamps) + add `media_batches.assignment_id FK assignments null` (C1). Guard with sqlite skip for tests.
4. Resolve DEC-008 vs devices: retain `devices` table, amend DEC-011 to un-defer devices for desk audit.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `database/migrations/2026_08_29_200001_fix_users_role_fk_restrict.php`
  - `database/migrations/2026_08_29_200002_add_assignments_and_batch_fk.php`
  - `database/migrations/2026_08_29_200003_append_only_grants.php`
  - `app/Models/Assignment.php` (new)
  - `app/Models/MediaBatch.php` (add assignment relation)
  - `docs/knowledge-inventory/decisions.md` DEC-011
- **Reference Files:**
  - `v1-database-design.md:317-330,81,33`
  - `database/migrations/2026_08_27_000013_alter_users_table_add_auth_fields.php:15`
  - `database/migrations/2026_08_27_000026_create_media_batches_table.php`

---

## 4. Prompt (For the Coding AI)
> Implement migrations per Contract. Use pgsql guards for CHECK/REVOKE. Keep sqlite green via conditional. Assignment model: HasFactory, fillable per spec, casts due_at datetime, shot_list array.

---

## 5. Test Criteria
- [ ] `php artisan migrate:fresh --seed` green (pgsql + sqlite)
- [ ] `users.role_id` deleting referenced role fails (restrict)
- [ ] `assignments` table exists, `media_batches.assignment_id` FK exists nullable
- [ ] pgsql grants: app role cannot UPDATE audit_logs (manual psql check or migration comment)
- [ ] `php -l` clean

---

## 6. Completion Notes
- **Shipped:** `2026_08_29_200001_fix_users_role_fk_restrict.php` (RESTRICT), `200002_add_assignments_and_batch_fk.php` (assignments + media_batches.assignment_id), `200003_append_only_grants.php` (REVOKE on 5 tables), `Assignment.php` + `AssignmentFactory.php`, `MediaBatch.php` assignment relation, DEC-011.
- **Tests:** `migrate:fresh --seed` green, `php artisan test` 114 pass, `php -l` clean.
- **Live Smoke:** `config:clear/route:clear/view:clear/cache:clear` cleared.
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
