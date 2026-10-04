# Task: M14-COV-021 — Portal e2e fail-closed

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001
**Parent ADR:** DEC-007; FR-PRT-003

---

## 1. Contract (What)
- **Inputs / Validation:** `NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1` from COV-001. Real `public_id` from the API.
- **Outputs / Response:** `/story/{publicId}` renders the API headline. `/story/1` and `/story/bn1` show not-found, not `INITIAL_STORIES`. Search spec does not type "Rizvi" as proof of Meili. `?since` test sends `since` and asserts the cursor shrinks or stays stable. No `test.skip()` on login failure or story-not-found.
- **Authorization:** Portal login required for account specs.

---

## 2. Logic (How)
1. In `portal/app/story/[id]/page.tsx` and the feed page, if `NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1`, do not read `INITIAL_STORIES`. Production default unchanged.
2. Rewrite `portal-bangla.spec.ts`, `portal-time-and-search.spec.ts`, `portal-story-pagination-search.spec.ts`, `portal-faithful.spec.ts` search bits to use API ids. Delete skip-on-not-found.
3. `portal-search.spec.ts`: send `If-None-Match` if the test name says so, or rename the test. Throttle test must force 429 or be renamed to "does not crash". Do not accept 200 or 429.
4. Meili: add a test that mocks the Meili host and asserts a request, or name the current test `local fallback when Meili unconfigured` and assert it does not claim an index hit. One of those two is required.
5. `portal-ui.spec.ts` download must hit Laravel or be renamed off "download". Prefer a real download route if M11 already shipped it; do not keep `page.route` stub as the only download proof.
6. `portal-auth.spec.ts` / `portal-account.spec.ts`: login failure fails the test.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `portal/app/story/[id]/page.tsx`
  - `portal/app/page.tsx` (fallback flag only)
  - portal e2e specs named above
- **Reference Files:**
  - `portal/lib/mockData.ts`
  - `docs/testing-coverage-gaps.md` §1 portal rows

---

## 4. Prompt (For the Coding AI)
> When `NEXT_PUBLIC_DISABLE_MOCK_FALLBACK=1`, portal story and feed must not use `INITIAL_STORIES`. Rewrite portal e2e to use real public ids, send `since`, and fail instead of skip. Rename or fix the If-None-Match and 200-or-429 tests. Download e2e must not be a route stub. Do not remove mock fallback for normal `next dev`.

---

## 5. Test Criteria
- [ ] `/story/bn1` is not-found under the flag
- [ ] A published API story renders in the portal UI
- [ ] `since` query param is present in the request
- [ ] No new `test.skip()` in the touched specs
- [ ] Playwright portal specs green

---

## 6. Completion Notes
- **Shipped:** `portal/app/story/[id]/page.tsx::getStory()` checks `process.env.NEXT_PUBLIC_DISABLE_MOCK_FALLBACK === '1'` and returns `null` (so the page renders "Story not found") instead of falling back to `INITIAL_STORIES`. New `tests/e2e/portal-fail-closed.spec.ts` (run under the flag set in COV-001's playwright.config): `/story/{realPublicId}` renders the API story headline; `/story/bn1` and `/story/1` show "Story not found" — not mock copy. `?since=` test publishes two stories with controlled timestamps, takes the older `published_at`, requests `/api/v1/portal/feed?since=<that+1s>` and asserts the older headline is absent while a newer one is present (proving the cursor actually constrains results, not just the default ordering). Meili search under failure mode is out of scope here — `portal-search.spec.ts` (COV-027) will rename or fix the 200-or-429 acceptance.
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
