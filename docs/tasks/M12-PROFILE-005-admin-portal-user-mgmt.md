# Task: M12-PROFILE-005 — Admin-side portal user management (FR-CLT-002)

**Status:** ⏳ Pending
**Dependencies:** M12-PROFILE-003 (PortalAuthController, Sanctum for ClientUser)
**Parent ADR:** FR-CLT-002 (invite portal users, assign client role, deactivate)

---

## 1. Contract (What)
- **Inputs:**
  - Admin actions on clients drawer → "Portal Users" tab:
    - **Invite:** `name`, `email`, `client_role_id` (dropdown of `type='client'` roles) → sends invite email with one-time password link
    - **Deactivate:** sets `client_users.status = 'deactivated'` → kills active sessions (revokes all Sanctum tokens)
    - **Reactivate:** sets `client_users.status = 'active'`
    - **Resend invite:** re-sends invite email (if status = `invited`)
    - **Edit role:** update `client_role_id` (dropdown)
- **Outputs:** Livewire actions with toast confirmations; audit log entries for invite/deactivate/reactivate
- **Authorization:** RBAC `clients.edit` permission required. `assertCan(user, 'clients', 'edit')` on every action.
- **Service:** `PortalAccountService` (NFR §15.1) — `invite(client, data)`, `deactivate(clientUser)`, `reactivate(clientUser)`, `resendInvite(clientUser)`, `updateRole(clientUser, roleId)`.

---

## 2. Logic (How)
1. Create `App\Services\PortalAccountService`:
   - `invite(Client $client, array $data)`: create `client_users` row with `status = 'invited'`, generate random password (hashed), dispatch `SendPortalInvite` mailable (contains login URL + one-time password). Log `audit_log` entry.
   - `deactivate(ClientUser $user)`: set `status = 'deactivated'`, revoke all Sanctum tokens (`$user->tokens()->delete()`). Log audit.
   - `reactivate(ClientUser $user)`: set `status = 'active'`. Log audit.
   - `resendInvite(ClientUser $user)`: re-dispatch invite mailable (new one-time password). Log audit.
   - `updateRole(ClientUser $user, int $roleId)`: update `client_role_id`. Log audit.
2. Create `App\Mail\SendPortalInvite` — Blade template with client name, portal URL, one-time password, "Login & change password" CTA.
3. Update `App\Livewire\Admin\ClientsManager`:
   - Add `$clientUsers` property (eager-loaded with client).
   - Add Livewire methods: `invitePortalUser()`, `deactivatePortalUser()`, `reactivatePortalUser()`, `resendPortalInvite()`, `updatePortalUserRole()`.
   - Add invite modal state: `$showPortalInviteModal`, `$portalInviteName`, `$portalInviteEmail`, `$portalInviteRoleId`.
4. Update `resources/views/livewire/admin/clients-manager.blade.php`:
   - Add "Portal Users" tab in the client drawer (alongside existing contacts/entitlements/channels sections).
   - Show user list: name, email, role chip, status badge (active/invited/deactivated), action buttons (deactivate/reactivate/resend/role dropdown).
   - Invite modal: name, email, client role dropdown, "Send invite" button.
   - Wire methods to Livewire actions.
5. Update `database/seeders/RoleSeeder.php` if needed: ensure at least one `type='client'` role exists for the dropdown.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Services/PortalAccountService.php`
  - `app/Mail/SendPortalInvite.php`
  - `resources/views/mail/portal-invite.blade.php`
  - `tests/Feature/PortalAccountTest.php`
- **Files to Modify:**
  - `app/Livewire/Admin/ClientsManager.php` (portal user methods + properties)
  - `resources/views/livewire/admin/clients-manager.blade.php` (portal users tab + invite modal)
  - `database/seeders/RoleSeeder.php` (ensure client role exists)
  - `docs/knowledge-inventory/domain.md` (update client users section in same commit)
- **Reference:**
  - `app/Livewire/Admin/RolesManager.php` (invite/deactivate pattern for staff — lines 467–506 invite modal, lines 216–219 deactivate/reactivate)
  - `app/Models/ClientUser.php` (model, Sanctum tokens from M12-PROFILE-003)
  - `app/Models/Role.php` (scope by `type = 'client'`)
  - `resources/views/livewire/admin/clients-manager.blade.php` lines 263–269 (existing clientUsers display)
  - `app-data/v1-functional-requirements.md` FR-CLT-002 (lines 428–430)

---

## 4. Prompt (For the Coding AI)
> Implement M12-PROFILE-005. Create PortalAccountService with invite/deactivate/reactivate/resendInvite/updateRole methods. Create SendPortalInvite mailable with portal login URL + one-time password. Update ClientsManager Livewire component with portal user methods + invite modal. Update clients-manager.blade.php with "Portal Users" tab in client drawer showing user list with status badges and action buttons. All actions write audit_log entries. Deactivate revokes Sanctum tokens. Ensure client role exists in RoleSeeder. Create tests/Feature/PortalAccountTest.php. Update domain.md.

---

## 5. Test Criteria
- [ ] Invite creates `client_users` row with `status = 'invited'` and sends email
- [ ] Invite with duplicate email returns validation error
- [ ] Deactivate sets status to 'deactivated' and revokes all Sanctum tokens
- [ ] Reactivate sets status to 'active'
- [ ] Resend invite re-sends email (test via Mail::fake)
- [ ] Update role changes `client_role_id`
- [ ] RBAC: non-`clients.edit` user gets 403
- [ ] Audit log entries written for all actions
- [ ] Portal users tab visible in clients drawer with correct data
- [ ] `php artisan test` green; `php -l` clean; live smoke invite + deactivate as editor role

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
