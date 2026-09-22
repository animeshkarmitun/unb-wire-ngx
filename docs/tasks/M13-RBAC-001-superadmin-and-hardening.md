# Task: M13-RBAC — Superadmin + RBAC Hardening

**Status:** ✅ Completed
**Dependencies:** None
**Parent ADR:** N/A — RBAC hardening + superadmin concept

---

## Current State

### What exists (solid foundation)
- `Role` model: `roles` table (name, type=system|custom|client, description, is_locked)
- `RolePermission` model: `role_permissions` table (role_id, module, can_view/create/edit/publish/delete)
- `RbacService`: `can(user, module, action)` + `assertCan()` — checks role permissions
- `RoleSeeder`: 8 roles (Admin=system locked, Editor, Strategist, Admin Report, Business Team, Client Bangla, Uploader-Bangla, Uploader-English)
- `RolesManager` Livewire: create/edit/duplicate/delete roles, invite members, assign roles, permission matrix with presets
- 10 permission modules: stories, stories_bn, media, clients, packages, distribution, settings, ai, history, audit
- 13 RBAC unit tests in `RbacServiceTest`

### What's missing

| Gap | Severity | Detail |
|-----|----------|--------|
| No superadmin concept | High | Admin role is just another role with full perms. No `is_superadmin` flag. Any user with `settings,edit` can modify any role including Admin. |
| No protection for last admin | High | Admin can demote themselves or other admins — no guard against locking out all superadmins. |
| 4 Livewire components missing rbac | High | PhotoManager, ApPhotoManager, DistributionLog, ServiceConfig — write actions have no permission check. |
| Locked role bypass | Medium | `is_locked` prevents editing in UI, but `saveRole()` checks it. Need to verify `deleteRole()` also checks. |
| No route-level test for forbidden roles | Medium | Only `/admin/add-news` tested with wrong role. 12 other routes untested. |
| Dashboard accessible to all | Low | `/admin/dashboard` has no rbac check — likely intentional, needs confirmation. |
| Preferences/Notifications no rbac | Low | Open to all authenticated users — likely intentional, needs confirmation. |

---

## 1. Contract (What)

### Superadmin Definition
- A user with `is_superadmin = true` on the `users` table (boolean column)
- Superadmin bypasses all RBAC checks — `RbacService::can()` returns `true` for superadmins
- Superadmin is the ONLY role that can:
  - Create/delete system (`is_locked=true`) roles
  - Modify permissions of system roles
  - Assign/revoke the superadmin flag to other users
  - Delete users with superadmin flag
- At least one superadmin must always exist (guard against self-demotion)

### Permission Matrix (unchanged modules)
```
stories, stories_bn, media, clients, packages, distribution, settings, ai, history, audit
Actions: view, create, edit, publish, delete
```

### RBAC Coverage (after hardening)
Every Livewire component that writes data must check `RbacService::assertCan()`:
- PhotoManager → `media,edit` / `media,delete`
- ApPhotoManager → `media,edit`
- DistributionLog → `distribution,edit`
- ServiceConfig → `settings,edit`

---

## 2. Logic (How)

### Step 1: Migration — add `is_superadmin` to users
```php
$table->boolean('is_superadmin')->default(false)->after('status');
```

### Step 2: Update `User` model
- Add `is_superadmin` to `$fillable` and `$casts`
- Add `isSuperAdmin(): bool` method

### Step 3: Update `RbacService::can()`
```php
public function can(User $user, string $module, string $action): bool
{
    if ($user->is_superadmin) {
        return true;
    }
    // ... existing logic
}
```

### Step 4: Add rbac to 4 Livewire components
- `PhotoManager`: mount check `media,view`, approve/reject/delete → `media,edit`/`media,delete`
- `ApPhotoManager`: mount check `media,view`, attach → `media,edit`
- `DistributionLog`: mount check `distribution,view`, retry → `distribution,edit`
- `ServiceConfig`: mount check `settings,view`, save → `settings,edit`

### Step 5: Protect system roles in RolesManager
- `saveRole()`: if `role.is_locked` AND `!auth()->user()->is_superadmin` → abort 403
- `deleteRole()`: if `role.is_locked` AND `!auth()->user()->is_superadmin` → abort 403
- `createRole()`: allow `type=system` only for superadmin
- Show "System" badge with lock icon on locked roles in UI

### Step 6: Protect superadmin users
- `deactivateUser()`: if target is superadmin AND actor is not superadmin → abort 403
- `deleteRole()`: if role has superadmin members, only superadmin can delete
- Self-protection: superadmin cannot remove own superadmin flag if they're the last one

### Step 7: Superadmin management UI
- In RolesManager → People tab: show superadmin badge
- Toggle superadmin button (only visible to current superadmin)
- Confirmation modal: "Grant superadmin access?" / "Revoke superadmin access?"
- Cannot revoke if last superadmin

### Step 8: Seed — mark existing Admin users as superadmin
```php
// In RoleSeeder or separate migration
$adminRole = DB::table('roles')->where('name', 'Admin')->first();
if ($adminRole) {
    DB::table('users')->where('role_id', $adminRole->id)->update(['is_superadmin' => true]);
}
```

### Step 9: Tests
- Test superadmin bypasses all rbac checks
- Test non-superadmin cannot modify locked roles
- Test non-superadmin cannot grant/revoke superadmin
- Test last superadmin cannot self-demotion
- Test PhotoManager/ApPhotoManager/DistributionLog/ServiceConfig with wrong role → 403
- Test each admin route with forbidden roles (systematic sweep)

### Step 10: Knowledge-inventory sync
- Update `docs/knowledge-inventory/domain.md` with superadmin concept
- Update `docs/knowledge-inventory/architecture.md` with RBAC hardening details

---

## 3. Context (Where)

### Files to Create
- `database/migrations/xxxx_add_is_superadmin_to_users_table.php`
- `tests/Feature/SuperadminTest.php`
- `tests/Feature/RbacEndpointTest.php` (systematic route sweep)

### Files to Modify
- `app/Models/User.php` — add `is_superadmin` field + `isSuperAdmin()` method
- `app/Services/RbacService.php` — superadmin bypass
- `app/Livewire/Admin/PhotoManager.php` — add rbac checks
- `app/Livewire/Admin/ApPhotoManager.php` — add rbac checks
- `app/Livewire/Admin/DistributionLog.php` — add rbac checks
- `app/Livewire/Admin/ServiceConfig.php` — add rbac checks
- `app/Livewire/Admin/RolesManager.php` — superadmin protection + UI
- `resources/views/livewire/admin/roles-manager.blade.php` — superadmin badge + toggle
- `database/seeders/RoleSeeder.php` — mark admin users as superadmin

### Reference Files
- `app-data/v1-non-functional-requirements.md` (§16.1 RBAC)
- `app-data/v1-functional-requirements.md` (FR-ACC-001…004)
- `docs/knowledge-inventory/domain.md`
- `docs/knowledge-inventory/architecture.md`

---

## 4. Prompt (For the Coding AI)

> Implement superadmin concept and RBAC hardening for UNB Wire.
>
> **Context:** RBAC is solid but has gaps: no superadmin flag, 4 Livewire components missing permission checks, no protection for system roles beyond UI lock.
>
> 1. Create migration adding `is_superadmin` boolean (default false) to `users` table.
>
> 2. Update `User` model: add `is_superadmin` to fillable/casts, add `isSuperAdmin(): bool` method.
>
> 3. Update `RbacService::can()`: if `$user->is_superadmin` return true before checking permissions.
>
> 4. Add rbac to PhotoManager: `mount()` → `assertCan(user, 'media', 'view')`, approve/reject methods → `assertCan(user, 'media', 'edit')`, delete → `assertCan(user, 'media', 'delete')`.
>
> 5. Add rbac to ApPhotoManager: `mount()` → `assertCan(user, 'media', 'view')`, attach → `assertCan(user, 'media', 'edit')`.
>
> 6. Add rbac to DistributionLog: `mount()` → `assertCan(user, 'distribution', 'view')`, retry → `assertCan(user, 'distribution', 'edit')`.
>
> 7. Add rbac to ServiceConfig: `mount()` → `assertCan(user, 'settings', 'view')`, save → `assertCan(user, 'settings', 'edit')`.
>
> 8. In RolesManager: protect `saveRole()` and `deleteRole()` — if role.is_locked AND !actor.is_superadmin → abort 403. In `createRole()`, allow type='system' only for superadmin.
>
> 9. In RolesManager: add superadmin protection to `deactivateUser()` — if target.is_superadmin AND !actor.is_superadmin → abort 403. Add `toggleSuperadmin(userId)` method that grants/revokes is_superadmin flag with last-superadmin guard.
>
> 10. In roles-manager.blade.php: show superadmin badge next to user names in People tab. Add toggle superadmin button (visible only to current superadmin). Add confirmation modal.
>
> 11. In RoleSeeder: after creating roles, set `is_superadmin = true` for users with Admin role.
>
> 12. Create `tests/Feature/SuperadminTest.php`: test superadmin bypasses rbac, test non-superadmin cannot modify locked roles, test non-superadmin cannot toggle superadmin, test last superadmin guard.
>
> 13. Create `tests/Feature/RbacEndpointTest.php`: for each admin route, test that a user without the required rbac permission gets 403.
>
> 14. Update `docs/knowledge-inventory/domain.md` and `architecture.md` with superadmin concept.
>
> Follow existing code style. No comments. Run `php artisan test` and `php -l` on all changed files.

---

## 5. Test Criteria

- [ ] `php artisan test --filter=SuperadminTest` passes
- [ ] `php artisan test --filter=RbacEndpointTest` passes
- [ ] `php artisan test --filter=RbacServiceTest` still passes
- [ ] `php artisan test --filter=RolesManagerTest` still passes
- [ ] `php artisan test` full suite green
- [ ] `php -l` clean on all modified PHP files
- [ ] Superadmin can access all routes regardless of role permissions
- [ ] Non-superadmin cannot edit/delete locked roles
- [ ] Non-superadmin cannot toggle superadmin flag
- [ ] Last superadmin cannot remove own superadmin flag
- [ ] PhotoManager/ApPhotoManager/DistributionLog/ServiceConfig return 403 for unauthorized users
- [ ] Every admin route returns 403 for users without required rbac permission

---

## 6. Completion Notes

- **Shipped:** 2026-09-13
- **Tests:** 12 SuperadminTest + 16 RbacEndpointTest pass
- **Live Smoke:** verified superadmin bypass, locked role protection, last-superadmin guard
- **Review:** self-reviewed

---

## 7. Prompt Ready?

- [x] Yes
