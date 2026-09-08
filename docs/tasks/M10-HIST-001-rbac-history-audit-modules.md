# Task: M10-HIST-001 — RBAC `history` + `audit` permission modules

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-012 + `docs/plans/history-audit-design.md` §5

---

## 1. Contract (What)
- **Inputs / Validation:** Two new `role_permissions` module rows per role: `history`, `audit` (seed data only — **no schema change**). Grants per design §5: `history` view = Admin, Editor, Strategist, Admin Report, Uploader-Bangla, Uploader-English; `audit` view = Admin, Admin Report. All other cells 0.
- **Outputs / Response:** `RbacService::can($user, 'history'|'audit', 'view')` returns per matrix after `migrate:fresh --seed`; RolesManager permission-matrix drawer renders the two new module rows.
- **Authorization:** Existing `RbacService` untouched (module-driven — no code change needed for checks).

---

## 2. Logic (How)
1. `RoleSeeder::$modules` += `['history', 'audit']`; extend `$perms` matrix per design §5 table (view-only: `[1,0,0,0,0]` or `[0,0,0,0,0]`).
2. Verify RolesManager drawer/matrix presets — if module list is hardcoded in `RolesManager.php` or `roles-manager.blade.php`, add the two rows; if DB-driven, no change.
3. Log **DEC-012** in `docs/knowledge-inventory/decisions.md` (same commit — docs sync gate).
4. Seeder idempotency: `updateOrInsert` already handles reruns.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `database/seeders/RoleSeeder.php`
  - `app/Livewire/Admin/RolesManager.php` + `resources/views/livewire/admin/roles-manager.blade.php` (only if module list hardcoded)
  - `docs/knowledge-inventory/decisions.md` (DEC-012)
  - `tests/Feature/RbacTest.php` (extend)
- **Reference Files:** `app/Services/RbacService.php`, `docs/plans/history-audit-design.md` §5

---

## 4. Prompt (For the Coding AI)
> Add `history` and `audit` modules to RoleSeeder permission matrix per design §5 (view-only grants; Business Team and Client Bangla get 0). Check RolesManager for hardcoded module lists and update. Log DEC-012 in decisions.md. Extend RbacTest: Editor can view history not audit; Admin Report can view audit; Business Team denied history; Client Bangla denied both. No migrations.

---

## 5. Test Criteria
- [ ] `php artisan migrate:fresh --seed` idempotent, grants match design §5 exactly
- [ ] Feature test: `can('history','view')` true for Editor/Strategist/Admin Report/Uploaders/Admin
- [ ] Feature test: `can('audit','view')` false for Editor/Strategist/Business/Client, true for Admin/Admin Report
- [ ] RolesManager matrix shows both new module rows
- [ ] `php scripts/schema-parity-check.php` green (no schema touched)

---

## 6. Completion Notes
- **Shipped:** —
- **Tests:** —
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
