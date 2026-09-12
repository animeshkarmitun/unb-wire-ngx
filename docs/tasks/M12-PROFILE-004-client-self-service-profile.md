# Task: M12-PROFILE-004 — Client self-service profile & password (portal `/account`)

**Status:** ⏳ Pending
**Dependencies:** M12-PROFILE-003 (portal session auth)
**Parent ADR:** FR-CLT-002 (portal user management), FR-PRT-001 (portal login)

---

## 1. Contract (What)
- **Inputs:**
  - `GET /api/v1/portal/profile` — returns authenticated client_user profile
  - `PATCH /api/v1/portal/profile` — `name` (max 120), `email` (unique in `client_users`, citext). Email change marks status → `pending_verification` until confirmed (or immediate if contract says "presentation only" — decision: immediate for now, no email verification for clients in v1 since FR-CLT-002 doesn't mention it)
  - `PATCH /api/v1/portal/password` — `current_password`, `password`, `password_confirmation` (min 8)
- **Outputs:**
  - Profile: `{ id, name, email, client_role: { name }, client: { name, initials, status }, last_login_at }`
  - Password: `200 { message: 'Password updated' }`
- **Authorization:** `EnsurePortalSession` middleware (M12-PROFILE-003). Client_user can only edit own profile.
- **Out of scope:** Email verification flow for clients (not in FR-CLT-002); timezone/display prefs for portal (portal uses browser IANA per FR-PRT-007).

---

## 2. Logic (How)
1. Add `GET /api/v1/portal/profile`, `PATCH /api/v1/portal/profile`, `PATCH /api/v1/portal/password` routes under `portal.session` middleware group (M12-PROFILE-003).
2. Extend `PortalAuthController` (or create `PortalProfileController`) with `show`, `update`, `updatePassword` methods.
3. `update`: validate name/email, check email uniqueness scoped to `client_users` (exclude self), update `client_users` row. No email verification in v1.
4. `updatePassword`: validate `current_password` against `client_users.password`, then update. Standard Laravel `Hash::check` + `Hash::make`.
5. Portal side: create `portal/app/account/page.tsx`:
   - Fetch profile on mount (`GET /api/v1/portal/profile` with Bearer token).
   - Show: name, email, role, client org, last login.
   - Edit: inline name/email edit with save button.
   - Password section: current + new + confirm fields with change button.
   - "Back to feed" link.
6. Update portal header user dropdown: add "My Account" link → `/account`.
7. Add `portal/lib/api.ts` helper for authenticated fetches (reuses `portalAuthHeaders()` from M12-PROFILE-003).

---

## 3. Context (Where)
- **Files to Create:**
  - `portal/app/account/page.tsx`
  - `portal/lib/api.ts` (authenticated fetch helper)
  - `tests/Feature/Api/PortalProfileTest.php`
- **Files to Modify:**
  - `app/Http/Controllers/Api/PortalAuthController.php` (or create `PortalProfileController.php`)
  - `routes/api.php` (profile/password routes under `portal.session`)
  - `portal/app/page.tsx` (add "My Account" link in user dropdown, line ~660)
  - `portal/lib/auth.ts` (export `portalAuthHeaders`)
- **Reference:**
  - `app/Http/Controllers/ProfileController.php` (staff profile pattern — Breeze)
  - `app/Models/ClientUser.php` (fillable, casts)
  - `portal/app/page.tsx` lines 605–695 (user dropdown shape)

---

## 4. Prompt (For the Coding AI)
> Implement M12-PROFILE-004. Add client self-service profile + password endpoints under portal.session middleware. GET /profile returns client_user info. PATCH /profile updates name/email. PATCH /password changes password (current_password verified). Create portal/app/account/page.tsx with profile view/edit + password change form. Add "My Account" link to portal header dropdown. Create portal/lib/api.ts for authenticated fetches. Create tests/Feature/Api/PortalProfileTest.php.

---

## 5. Test Criteria
- [ ] `GET /api/v1/portal/profile` returns client_user with client context
- [ ] `PATCH /api/v1/portal/profile` updates name; returns updated profile
- [ ] `PATCH /api/v1/portal/profile` with duplicate email returns 422
- [ ] `PATCH /api/v1/portal/password` with correct current_password updates password
- [ ] `PATCH /api/v1/portal/password` with wrong current_password returns 422
- [ ] Unauthenticated request to profile/password routes returns 401
- [ ] Portal `/account` page renders profile data and allows edit
- [ ] `php artisan test` green; `php -l` clean; live smoke portal `/account` as client_user

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
