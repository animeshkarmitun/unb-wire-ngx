# Task: M14-COV-014 — Form request and portal middleware negatives

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007; NFR §8

---

## 1. Contract (What)
- **Inputs / Validation:** Invalid AI assist body, invalid Tus create, login missing password, portal session cookie vs API key on the wrong route.
- **Outputs / Response:** 422 with field errors for bad requests. 401 when portal session middleware is required and absent. 401 when `ResolveClient` gets a bad key. Staff RBAC tests stay as they are.
- **Authorization:** Guest and wrong-credential cases.

---

## 2. Logic (How)
1. Extend `tests/Feature/FormRequestValidationTest.php` with 422 cases for `AiAssistRequest` and `TusCreateRequest` (read the rules; do not invent fields).
2. Login 422 already may exist in `AuthenticationTest` — if not, add missing-password 422 there.
3. Name `EnsurePortalSession` and `ResolveClient` in a test method each: portal route without session → 401/302 as the route actually does; API route with a bad key → 401. Assert the status the route uses today. Do not change middleware order.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/Feature/FormRequestValidationTest.php`
  - `tests/Feature/MiddlewareTest.php`
- **Reference Files:**
  - `app/Http/Requests/`
  - `routes/api.php`
  - `app/Http/Middleware/`

---

## 4. Prompt (For the Coding AI)
> Add 422 tests for AI assist and Tus create form requests using their real rules. Add named tests for `EnsurePortalSession` and `ResolveClient` negative paths. Do not change middleware. Do not duplicate the staff RBAC matrix.

---

## 5. Test Criteria
- [ ] At least one 422 per named form request
- [ ] Bad API key is 401
- [ ] Missing portal session does not return 200
- [ ] `php artisan test --filter=FormRequestValidationTest` green

---

## 6. Completion Notes
- **Shipped:** `tests/Feature/FormRequestValidationTest.php` extended with direct rule checks for `AiAssistRequest` (unknown `story_id` → invalid; `text > 20000` → invalid; non-integer `story_id` → invalid) and `TusCreateRequest` (missing `upload_length` → invalid; negative `upload_length` → invalid; `evil.exe` filename → invalid). `tests/Feature/MiddlewareTest.php` extended with: `GET /api/v1/portal/profile` without session returns 401 (`EnsurePortalSession`), `GET /api/v1/feed` without an API key returns 401, `GET /api/v1/feed` with a bad bearer token returns 401.
- **Tests:** `php artisan test --filter='FormRequestValidationTest|MiddlewareTest'` 26 passed (55 assertions). `php artisan test` full suite 880 passed / 1 skipped (2802 assertions).
- **Live Smoke:** n/a.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
