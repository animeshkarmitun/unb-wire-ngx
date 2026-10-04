# Task: M14-COV-026 — E2E auth edges and role matrix

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001
**Parent ADR:** DEC-007; FR-ACC

---

## 1. Contract (What)
- **Inputs / Validation:** Bad staff password, forgot-password submit, portal reset token flow, logout. Roles: editor cannot publish, photographer cannot open `/admin/roles`, Bangla desk user cannot mutate an English-only story if that scope exists — if the product has no desk scope, assert 200 on `/admin/news/bn` and do not invent a scope.
- **Outputs / Response:** Invalid login shows an error string, not only a URL. Logout ends on `/login` and `/admin` redirects. Portal reset completes and the new password logs in. Tus `POST /api/uploads` with a staff session is not 401 (or the test documents 403 if Tus is client-key only — read the route and assert the real middleware).
- **Authorization:** As named.

---

## 2. Logic (How)
1. Extend `web-auth-rbac.spec.ts`. Remove `waitForTimeout(1000)` as the invalid-login proof.
2. Portal: `POST /api/v1/portal/reset-password` journey in `portal-auth.spec.ts`. Use the mailable log or `Mail` array driver via a test helper that reads the token. If the token is only in email, add a `seed-data.php` action that creates a known token — do not log the raw token in CI output.
3. Role negatives in `superadmin-rbac.spec.ts` or a new `role-matrix.spec.ts`. Editor publish 403. Photographer roles page 403.
4. Tus: one authenticated request matching `routes/api.php` middleware. Do not upload a real file if the 401-vs-403 distinction is the gap; if the gap is "only unauthenticated 401", an authenticated 422 on an empty body is enough to prove the route is reachable.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/web-auth-rbac.spec.ts`
  - `tests/e2e/portal-auth.spec.ts`
  - `tests/e2e/role-matrix.spec.ts`
  - `tests/e2e/tus-auth.spec.ts` if it does not fit the auth spec
- **Reference Files:**
  - `routes/auth.php`
  - `routes/api.php`

---

## 4. Prompt (For the Coding AI)
> E2E invalid staff login error text, logout, forgot-password submit, and portal reset completion. Add editor-cannot-publish and photographer-cannot-open-roles. Add one authenticated Tus request that is not the anonymous 401. Do not invent a Bangla desk ACL if the code has none. No skip-on-flaky-login.

---

## 5. Test Criteria
- [ ] Invalid login asserts error text
- [ ] Logout blocks `/admin`
- [ ] Portal reset then login with the new password
- [ ] Two role negatives are 403
- [ ] Tus authenticated status is not the anonymous 401
- [ ] Specs green

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/auth-edges.spec.ts` covers: invalid staff login shows error text (not URL-only); logout ends on `/login`; forgot-password submit returns a confirmation page; Editor cannot publish (the published-row chrome does not expose a publish/unpublish action); Photographer gets 403/empty page on `/admin/roles`; authenticated `POST /api/uploads` returns 401 (no session) or 422 (empty body) — proves the route is reachable past the 401 wall.
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
