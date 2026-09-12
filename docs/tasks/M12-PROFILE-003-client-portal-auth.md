# Task: M12-PROFILE-003 — Client portal auth: email/password login (FR-PRT-001, FR-CLT-002)

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** FR-PRT-001 (portal login), FR-CLT-002 (portal user management), M11-PORTAL-002 (current API-key auth)

---

## 1. Contract (What)
- **Inputs:**
  - `POST /api/v1/portal/login` — `email` + `password` (validated: email exists in `client_users`, password matches, status = `active`)
  - `POST /api/v1/portal/logout` — invalidates session token
  - `POST /api/v1/portal/forgot-password` — `email` → sends reset link (rate-limited: 3/min)
  - `POST /api/v1/portal/reset-password` — `token` + `email` + `password` + `password_confirmation`
- **Outputs:**
  - Login: `{ token, client_user: { id, name, email, client_role_id, client: { name, initials } } }` — Sanctum token or session cookie
  - Logout: `204`
  - Forgot/reset: `200 { message }` / `200 { message }`
- **Auth:** Unauthenticated for login/forgot/reset; authenticated for logout. Portal auth middleware (`EnsurePortalSession`) replaces API-key middleware on session-authenticated portal routes.
- **Coexistence:** API-key auth stays for programmatic clients (`/api/v1/feed`, `/api/v1/media/{id}/download`). Session auth is for browser portal only.

---

## 2. Logic (How)
1. **Laravel side:**
   - Add `HasApiTokens` trait to `ClientUser` model (Sanctum).
   - Create `App\Http\Controllers\Api\PortalAuthController` — `login`, `logout`, `forgotPassword`, `resetPassword`.
   - `login`: validate credentials against `client_users` table, check `status = 'active'`, issue Sanctum token, update `last_login_at`. Return token + user + client context (reuse `PortalController@context` shape).
   - `forgotPassword`: create `password_reset_tokens` row (reuse Laravel's built-in `PasswordBroker` with `client_users` table — may need custom broker or use `DB::table('password_reset_tokens')` directly since `client_users` is not the `users` table).
   - `resetPassword`: validate token, hash new password, update `client_users.password`, delete token row.
   - Create `EnsurePortalSession` middleware: authenticates via Sanctum token from `Authorization: Bearer` header, sets `$request->attributes->set('clientUser', $user)` and `$request->attributes->set('client', $user->client)`.
2. **Portal side (Next.js):**
   - Update `portal/components/LoginModal.tsx`: dual-mode — email/password form (default) + "Use API key" link (for programmatic clients). Email/password calls `POST /api/v1/portal/login`, stores token in `sessionStorage` (key: `unb_portal_token`).
   - Update `portal/lib/auth.ts`: add `getPortalToken()`, `setPortalToken()`, `clearPortalToken()`, `portalAuthHeaders()` (returns `Authorization: Bearer <token>`). Keep existing API-key functions.
   - Update `portal/app/page.tsx`: on mount, check for portal token first, then API key. If portal token found, fetch `/context` with Bearer token. Update header to show user name + "My Account" link (→ future `/account` page) instead of just client name.
   - Add `portal/components/ForgotPasswordModal.tsx` — email input, calls `/api/v1/portal/forgot-password`.
3. **Routes:** New group in `routes/api.php`:
   ```php
   Route::prefix('v1/portal')->group(function () {
       Route::post('/login', [PortalAuthController::class, 'login'])->middleware('throttle:10,1');
       Route::post('/forgot-password', [PortalAuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
       Route::post('/reset-password', [PortalAuthController::class, 'resetPassword'])->middleware('throttle:5,1');
       // Existing API-key routes stay unchanged
   });
   Route::prefix('v1/portal')->middleware(['portal.session', 'throttle:60,1'])->group(function () {
       Route::post('/logout', [PortalAuthController::class, 'logout']);
       // Future: profile, password change routes (M12-PROFILE-004)
   });
   ```

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Http/Controllers/Api/PortalAuthController.php`
  - `app/Http/Middleware/EnsurePortalSession.php`
  - `portal/components/ForgotPasswordModal.tsx`
  - `tests/Feature/Api/PortalAuthTest.php`
- **Files to Modify:**
  - `app/Models/ClientUser.php` (add `HasApiTokens`, `sendPasswordResetNotification` or custom)
  - `portal/components/LoginModal.tsx` (dual-mode: email/password + API key)
  - `portal/lib/auth.ts` (portal token functions)
  - `portal/app/page.tsx` (prefer portal token, show user name)
  - `routes/api.php` (new auth routes + middleware group)
  - `bootstrap/app.php` or `app/Http/Kernel.php` (register `portal.session` alias)
- **Reference:**
  - `app/Http/Middleware/EnsureClientApiKey.php` (API-key auth pattern)
  - `app/Http/Controllers/Api/PortalController.php` (context endpoint shape)
  - `database/migrations/2026_08_27_000017_create_client_users_table.php` (schema)
  - `app-data/v1-functional-requirements.md` FR-PRT-001 (lines 445–447), FR-CLT-002 (lines 428–430)

---

## 4. Prompt (For the Coding AI)
> Implement M12-PROFILE-003. Add email/password portal auth alongside existing API-key auth. Create PortalAuthController with login/logout/forgot-password/reset-password. Add HasApiTokens to ClientUser. Create EnsurePortalSession middleware (Sanctum). Update LoginModal.tsx to support dual-mode (email/password default, API-key fallback). Add portal token functions to auth.ts. Update page.tsx to prefer portal token over API key, show user name in header. Add ForgotPasswordModal.tsx. Create tests/Feature/Api/PortalAuthTest.php covering login success/failure, logout, forgot-password rate limit, reset-password flow. API-key routes stay unchanged.

---

## 5. Test Criteria
- [ ] `POST /api/v1/portal/login` with valid email+password returns token + client_user + client context
- [ ] `POST /api/v1/portal/login` with wrong password returns 422
- [ ] `POST /api/v1/portal/login` with deactivated user returns 403
- [ ] `POST /api/v1/portal/logout` with Bearer token invalidates session, returns 204
- [ ] `POST /api/v1/portal/forgot-password` sends reset link (or returns 200 even if email not found — no enumeration)
- [ ] `POST /api/v1/portal/reset-password` with valid token updates password
- [ ] Existing API-key routes (`/api/v1/feed`, `/api/v1/portal/context` with X-API-Key) still work
- [ ] Portal LoginModal shows email/password form by default, "Use API key" link switches mode
- [ ] `php artisan test` green; `php -l` clean; live smoke portal login/logout

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
