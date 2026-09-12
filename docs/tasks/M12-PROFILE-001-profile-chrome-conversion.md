# Task: M12-PROFILE-001 — Profile page admin-chrome conversion + remove self-delete

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** FR-ACC-002 (staff deactivation is admin-side), M8 faithful-conversion chrome (`x-admin-layout`, design tokens)

---

## 1. Contract (What)
- **Inputs:** Same Breeze routes — `GET /profile` (`profile.edit`), `PATCH /profile` (`profile.update`), password update routes. No new endpoints.
- **Outputs:** `/profile` renders inside `x-admin-layout` (sidebar/topnav/toast, UNB tokens) with two sections: profile information (name/email) and password change. Visual language matches `/admin/preferences`.
- **Removed:** Self-service account deletion — `DELETE /profile` route, `ProfileController@destroy`, and the delete-account card. Rationale: FR-ACC-002 deactivation is an admin action (kills sessions + device tokens immediately, audited); staff self-delete bypasses chain-of-custody and leaves stories/notes orphaned without an admin record.
- **Authorization:** `auth` + `verified` middleware (unchanged). Any authenticated staff role may edit own profile.

---

## 2. Logic (How)
1. Rebuild `resources/views/profile/edit.blade.php` on `x-admin-layout` with `<x-slot:title>`; restyle/rebuild the three partials with `x-btn` / `x-toast` and panel classes (`bg-panel border rounded-xl`) per `livewire/admin/preferences.blade.php`.
2. Delete the delete-user card from the view; remove `ProfileController@destroy`; remove `Route::delete('/profile', ...)` from `routes/web.php`. Keep `profile.edit` / `profile.update` URIs unchanged (topnav link + `PasswordUpdateTest` redirects depend on them).
3. Keep Breeze email-change semantics: changing email nulls `email_verified_at`.
4. `User::SoftDeletes` stays (admin deactivation path in RolesManager uses it) — only the self-service trigger is removed.

---

## 3. Context (Where)
- **Files to Modify:**
  - `resources/views/profile/edit.blade.php` (rewrite on `x-admin-layout`)
  - `resources/views/profile/partials/update-profile-information-form.blade.php` (restyle)
  - `resources/views/profile/partials/update-password-form.blade.php` (restyle)
  - `app/Http/Controllers/ProfileController.php` (remove `destroy`)
  - `routes/web.php` (remove `Route::delete('/profile', ...)`)
  - `tests/Feature/ProfileTest.php` (drop delete tests, assert `DELETE /profile` → 404, keep display/update tests green)
- **Reference:**
  - `resources/views/admin/preferences.blade.php` + `resources/views/livewire/admin/preferences.blade.php` (chrome pattern)
  - `resources/views/components/topnav.blade.php` (line ~59 links `profile.edit`)
  - `app/Livewire/Admin/RolesManager.php` (admin deactivation — the retained path)
- **Tests:**
  - Update `tests/Feature/ProfileTest.php` — display, update, email-verification-reset, `DELETE /profile` 404
  - `tests/Feature/Auth/PasswordUpdateTest.php` must stay green (redirects to `/profile`)

---

## 4. Prompt (For the Coding AI)
> Implement M12-PROFILE-001. Rebuild resources/views/profile/edit.blade.php on x-admin-layout with UNB panel styling, keeping profile-info and password sections; remove the delete-account card, ProfileController@destroy, and the DELETE /profile route. Keep GET/PATCH /profile URIs unchanged. Update tests/Feature/ProfileTest.php (remove delete tests, assert DELETE /profile is 404) and keep PasswordUpdateTest green.

---

## 5. Test Criteria
- [ ] `GET /profile` as staff renders admin chrome (sidebar/topnav present, no Breeze `x-app-layout` markup)
- [ ] Name/email update works; email change resets `email_verified_at`
- [ ] Password change works and redirects to `/profile`
- [ ] `DELETE /profile` returns 404; no `profile.destroy` route registered (`php artisan route:list --name=profile`)
- [ ] `php artisan test` green (ProfileTest + PasswordUpdateTest); `php -l` clean; live smoke `/profile` as editor role

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
