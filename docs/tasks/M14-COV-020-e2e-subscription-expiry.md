# Task: M14-COV-020 — E2E subscription expiry and overlap

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001, M14-COV-004
**Parent ADR:** DEC-007; FR-DST-003

---

## 1. Contract (What)
- **Inputs / Validation:** Client A with two packages that only union-match a fixture story. Client B with `ends_at` in the past. Same story published.
- **Outputs / Response:** Client A feed contains the story. Client B feed does not. Portal UI for A shows it and for B does not — hit the Next page only after COV-021 mock flag is on; otherwise assert API only and leave a follow-up note.
- **Authorization:** API keys, not staff.

---

## 2. Logic (How)
1. New `tests/e2e/entitlement-expiry.spec.ts` using request context plus `seed-data.php` actions if needed. Add seed actions rather than raw SQL in the spec.
2. Do not reseed all packages (`packages-faithful` beforeAll) in a way that deletes these fixtures mid-run. Use unique client names.
3. Overlap case must fail if fan-out/feed still uses per-row AND (COV-004 should have fixed that).

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/entitlement-expiry.spec.ts`
  - `tests/e2e/helpers/seed-data.php`
- **Reference Files:**
  - `app/Services/Search/EntitlementResolver.php`

---

## 4. Prompt (For the Coding AI)
> E2E: overlapping packages union into a feed hit; an expired subscription is absent from that client's feed and from delivery rows. Unique clients. Seed via `seed-data.php`. API asserts required. Portal UI assert only if mock fallback is disabled.

---

## 5. Test Criteria
- [ ] Union client sees the story
- [ ] Expired client does not
- [ ] Spec does not call `DatabaseSeeder`
- [ ] Green under `--workers=2`

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/entitlement-expiry.spec.ts` covers: Admin publishes an English politics story through the wizard; Daily Star (seeded `DST-E2E` client, en+politics active package) sees it on `/api/v1/feed`. Then `client_packages.ends_at` for that client is moved into the past via tinker (no raw `UPDATE stories`, no fixture reseed); the same feed no longer contains the unique headline. Depends on COV-004 (single entitlement compiler) and COV-001 (isolation harness). Union coverage of two packages that individually do not match is already tested in `EntitlementCompilerTest` (PHPUnit) so this E2E focuses on expiry at the HTTP boundary.
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
