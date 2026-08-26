# Smoke Checklist: AUTH — Panel Login & RBAC

> Verify authentication, session handling, and role-based policy enforcement.

---

## Steps

1. **Super Admin Login:**
   - Navigate to `/admin/login`.
   - Log in with Super Admin credentials.
   - Verify dashboard renders with all resource links accessible.

2. **Editor Login & Permissions:**
   - Log in with Desk Editor credentials.
   - Verify access to Articles, Categories, Media.
   - Verify system settings / user management tabs are hidden or protected (403).

3. **Reporter Login & Boundary:**
   - Log in with Reporter credentials.
   - Verify ability to create drafts and edit own drafts.
   - Verify inability to publish directly or edit other reporters' drafts.

4. **Invalid Credentials & Rate Limiting:**
   - Submit wrong password 5 times.
   - Verify rate limiting response (429 Too Many Requests).
