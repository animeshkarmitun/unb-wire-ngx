# Task: M11-PORTAL-002 — Client portal authentication

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** DEC-007 (client API keys), NFR §8

---

## 1. Contract (What)
- **Inputs:** Client API key entered in portal login form
- **Outputs:** Session with client identity stored in cookie/localStorage, used for all subsequent API calls
- **Auth flow:**
  1. Portal shows login form (API key input).
  2. Client enters API key → portal calls `POST /api/v1/portal/search-token` with `X-API-Key` header.
  3. If valid: store key + client info in Next.js state/cookie. Show client name + initials in header.
  4. If invalid: show error.
  5. Subsequent API calls include `X-API-Key` header automatically.
- **Guest mode:** Without login, portal shows public feed (default entitlements).

---

## 2. Logic (How)
1. Create `portal/components/LoginModal.tsx` — API key input form.
2. Create `portal/lib/auth.ts` — store/retrieve API key from `sessionStorage` or cookie.
3. Update `portal/app/page.tsx`:
   - On mount: check if API key stored. If yes, fetch `/context` with key → display client info.
   - If no key: show guest mode with "Login" button in header.
4. Update all API fetch calls to include `X-API-Key` header from stored key.
5. Replace hardcoded `CLIENT_INFO` import with dynamic context fetch.
6. Add logout (clear stored key).

---

## 3. Context (Where)
- **Files to Create:**
  - `portal/components/LoginModal.tsx`
  - `portal/lib/auth.ts`
- **Files to Modify:**
  - `portal/app/page.tsx` (replace `CLIENT_INFO` import, add auth state, add header to fetches)
  - `portal/lib/search.ts` (pass API key header in search-token request)
- **Reference:**
  - `app/Http/Middleware/EnsureClientApiKey.php` (validates `X-API-Key` header)
  - `portal/lib/mockData.ts` (`CLIENT_INFO` to be replaced)
- **Tests:**
  - Update `tests/e2e/portal-ui.spec.ts` — add login flow test

---

## 4. Prompt (For the Coding AI)
> Implement M11-PORTAL-002. Create portal auth using API keys stored in sessionStorage. Create LoginModal component. Update page.tsx to show guest/authenticated modes. Pass X-API-Key header in all API fetches. Remove CLIENT_INFO hardcoded import, replace with /context API call. Add E2E test for login flow.

---

## 5. Test Criteria
- [ ] Login with valid API key shows client name and quotas
- [ ] Login with invalid key shows error message
- [ ] Guest mode shows public feed without quotas
- [ ] API key persists across page refreshes (sessionStorage)
- [ ] Logout clears key and returns to guest mode
- [ ] Search-token request includes API key for tenant scoping

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
