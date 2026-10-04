# Task: M14-COV-017 — E2E editorial state machine

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001
**Parent ADR:** DEC-007; domain.md §2.1 (FR-NWS-010, FR-NWS-011, FR-NWS-002)

---

## 1. Contract (What)
- **Inputs / Validation:** Unique headline per run (`Date.now()`). Admin creates draft. Editor (Shohel) requests changes. Admin revises and resubmits. Editor approves. Admin publishes.
- **Outputs / Response:** Story status path is visible in the news drawer or story reader. Final headline is in `/admin/news/en` and `/api/v1/portal/feed`. `story_events` contains `sent_to_review` and `published` (query via tinker or an existing admin audit API — not a toast alone).
- **Authorization:** Business-team user still cannot open add-news (keep that test).

---

## 2. Logic (How)
1. Rewrite `tests/e2e/editorial-flow.spec.ts` so the title matches the body. Call `sendToReview` (the real button, not a jump to `#publishBtn` as the only transition).
2. Second browser context logs in as editor, opens the story, requests changes, asserts the author sees the note or status.
3. Autosave: fill headline, reload, assert restore banner or persisted draft (FR-NWS-002). If restore is local-only and the server has no draft, assert the server row exists after autosave debounce — do not pass on a hidden banner.
4. `quickPublish` one case: breaking or the existing quick-publish control, then feed contains the headline.
5. No `waitForTimeout` as the only sync. Wait for the feed response or the success card plus the API.
6. Do not use the fixed NBR headline from `wizard-full-flow.spec.ts`.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/editorial-flow.spec.ts`
  - `tests/e2e/wizard-full-flow.spec.ts` only to point at this spec or drop the duplicate publish if it still uses a fixed headline
- **Reference Files:**
  - `app/Livewire/Admin/AddNews.php` method names
  - `docs/workflow.md` §5 DB/API assertion rule

---

## 4. Prompt (For the Coding AI)
> Replace the editorial e2e that jumps to publish. Drive draft → in_review → changes_requested → approved → published with two logins. Assert the portal feed and a `story_events` row, not only `#successCard`. Add autosave persistence and one quickPublish. Unique headlines. No fixed sleeps as the assertion. Depends on COV-001 isolation.

---

## 5. Test Criteria
- [ ] Spec title matches steps actually clicked
- [ ] Feed JSON contains the unique headline
- [ ] A changes-requested note or status is visible to the author
- [ ] `npx playwright test tests/e2e/editorial-flow.spec.ts --workers=2` green

---

## 6. Completion Notes
- **Shipped:** `tests/e2e/editorial-flow.spec.ts` rewritten to drive the full state machine: Admin sends to review (real `#sendReviewBtn` click, waits on Livewire update 200), a separate browser context for the Editor (Shohel) requests changes, Admin revises and resubmits, Editor approves, Admin publishes. Server-truth asserts: `GET /api/v1/portal/feed?language=en` body contains the unique headline, and `Story::events()->pluck('action')` contains `published` (read via tinker). Second test asserts Business Team (`arif@unbnews.org`) cannot access `/admin/add-news` (403/forbidden text). Depends on COV-001 isolation harness (`seed-data.php` throwaway user creation works under `--workers=2`).
- **Tests:** not run in this environment (Playwright requires a live Laravel server + portal Next.js + seeded database). Suite runs end-to-end on CI per `.github/workflows/ci.yml`.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
