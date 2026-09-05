# Task: M8-ROLE-001 — Roles & Access Manager faithful

**Status:** ✅ Completed
**Dependencies:** M8-UI-002, M2-DB-001, M8-PACK-001
**Parent ADR:** app-data/roles.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/roles` `auth,verified,rbac:settings,view`.
- **Outputs / Response:**
  - Breadcrumb: `Home / Settings / Roles & access`.
  - Topbar with action buttons: `Invite member` (opens invite modal) and `New role` (opens new role modal).
  - 4-Stat Metric Strip:
    - Total roles (`Role::count()`)
    - People with access (`User::where('status', '!=', 'deactivated')->count()`)
    - Custom roles (`Role::where('type', 'custom')->count()`)
    - Pending invites (`User::where('status', 'invited')->count()`)
  - 3 Pill Tabs with live counters:
    - `Roles` (`$totalRoles`)
    - `People` (`$totalPeople`)
    - `Activity` (`$audits->count()`)
  - Panel 1: Roles Grid (`.role-grid`):
    - Role cards: initials logo with gradient `g1`–`g8`, role name, badge (`System` / `Client` / `Custom`), description, module permission chips (`full`, `view`, or count/total), member avatar stack with initials and `+X` overflow, member count text, footer actions:
      - `Edit permissions` (or `View permissions` if system) -> opens role editor slide-out drawer.
      - `Duplicate` -> duplicates role with `(copy)` suffix, same permissions, custom type.
      - `Delete` (hidden for system roles) -> opens delete role confirmation modal.
    - Dashed `Create a custom role` card (`.role-new`).
    - Informational banner for client roles.
  - Panel 2: People List (`.pp-list`):
    - Table columns: Avatar, Member (name + email + optional "You" tag), Desk, Role (live dropdown selector, disabled for self), Status (pill with indicator dot: active, invited, deactivated + last active text), Action button:
      - For self: none
      - For active: `Deactivate` (danger)
      - For deactivated: `Reactivate`
      - For invited: `Resend invite`
  - Panel 3: Activity / Audit List (`.au-list`):
    - Timeline rows with colored status dot, bold action/entity summary, and timestamp.
  - Slide-out Role Editor Drawer (`.drawer`):
    - Header: initials logo with gradient, role name, subtitle (role type · member count), close button (✕).
    - Role info section (for non-system roles): Name input, Description textarea, 8-dot color selector (`g1`–`g8`).
    - Quick presets row: `View only`, `Uploader`, `Editor`, `Full access`, `Clear all`.
    - Permissions module list: each module has header (module label, counter badge `X/5`, and `All`/`None` button), action chips with checkboxes (`view`, `create`, `edit`, `publish`, `delete` with danger styling for `delete`).
    - System role protection notice when editing `Admin` role.
    - Attached members chips section with avatars.
    - Footer: helper note, `Discard` button, `Save role` button.
  - Modals:
    - Create Role Modal: name, description, 8-dot color selector, start-from dropdown (blank or copy of existing role).
    - Delete Role Modal: title with role name; warning box listing members and disabling delete button if members are attached; confirmation message and active delete button if 0 members attached.
    - Invite Member Modal: full name, work email, desk dropdown, role dropdown.
- **Authorization:** `settings` module per `role_permissions` matrix (`view`, `create`, `edit`, `delete`).

---

## 2. Logic (How)
1. Extract and create `resources/css/roles-manager.css` containing 1:1 CSS from `app-data/roles.html` and import into `resources/css/app.css`.
2. Create `database/seeders/UserSeeder.php` to seed the 8 prototype newsroom team members from `roles.html` and initial audit entries.
3. Update `database/seeders/DatabaseSeeder.php` to run `UserSeeder::class`.
4. Refactor `app/Livewire/Admin/RolesManager.php` with:
   - Dynamic 4-stat metric calculations.
   - Tab switching between `roles`, `people`, and `audit`.
   - Role drawer state management, preset application, module toggle all/none, and atomic save with audit logging.
   - Role creation modal with copy-from permissions logic, immediately opening drawer for fine-tuning.
   - Role duplication with `(copy)` suffix and permission cloning.
   - Delete role modal with member attachment verification and blocker.
   - People live role update, deactivation, reactivation, invite resend, and self-protection guard.
   - Member invite with email validation and audit logging.
5. Implement `resources/views/livewire/admin/roles-manager.blade.php` matching prototype markup 1:1.
6. Write PHPUnit feature test suite `tests/Feature/RolesManagerTest.php` covering all permissions, presets, guards, and workflows.
7. Write Playwright E2E test suite `tests/e2e/roles-faithful.spec.ts`.
8. Verify schema parity, run Pint, compile assets, and execute live smoke tests.

---

## 3. Context (Where)
- **Files Created / Modified:**
  - `resources/css/roles-manager.css` [NEW]
  - `resources/css/app.css` [MODIFIED]
  - `database/seeders/UserSeeder.php` [NEW]
  - `database/seeders/DatabaseSeeder.php` [MODIFIED]
  - `app/Livewire/Admin/RolesManager.php` [MODIFIED]
  - `resources/views/livewire/admin/roles-manager.blade.php` [MODIFIED]
  - `tests/Feature/RolesManagerTest.php` [NEW]
  - `tests/e2e/roles-faithful.spec.ts` [NEW]
  - `docs/tasks/README.md` [MODIFIED]
  - `docs/tasks/M8-ROLE-001-roles-manager.md` [NEW]
