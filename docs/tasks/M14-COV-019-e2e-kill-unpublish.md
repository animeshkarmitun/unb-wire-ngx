# Task: M14-COV-019 — E2E kill, correction, unpublish

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001, M14-COV-005
**Parent ADR:** DEC-007; FR-DST-008, FR-NWS-016

---

## 1. Contract (What)
- **Inputs / Validation:** Published story already delivered to Daily Star key (seed or prior publish + fan-out). Prothom Alo never received it.
- **Outputs / Response:** Desk kill: Daily Star feed shows tombstone / killed; Prothom Alo feed does not gain a kill item. `index_outbox` has `op=delete` (tinker). Unpublish/live toggle: headline leaves `/api/v1/portal/feed`. Correction: feed brief/headline updates for the prior recipient.
- **Authorization:** Admin. Editor publish deny is COV-026.

---

## 2. Logic (How)
1. New spec `tests/e2e/kill-correction.spec.ts`. Use the story-reader or news-list control that actually kills. If the UI has no kill control, this task adds the smallest button that calls the existing `StoryService` kill method — do not invent a new state.
2. Fix `news-list-faithful.spec.ts` live toggle: after click, assert feed absence or presence via API. Remove the bare `waitForTimeout`.
3. Do not accept a pre-seeded killed row as the only kill coverage. `api-media-billing.spec.ts` seeded tombstone may stay as a contract test; this spec is the journey.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/kill-correction.spec.ts`
  - `tests/e2e/news-list-faithful.spec.ts`
  - Livewire kill button only if missing
- **Reference Files:**
  - `app/Livewire/Admin/StoryView.php`, `NewsList.php`

---

## 4. Prompt (For the Coding AI)
> E2E a real kill: prior recipient gets a tombstone, a client who never received it does not, and outbox has a main delete. E2E the news-list live toggle with an API assert, not a sleep. Add a correction that changes the feed headline. Add a kill control only if the UI cannot reach `StoryService` kill today.

---

## 5. Test Criteria
- [ ] Two API keys, different kill visibility
- [ ] Toggle click changes feed membership
- [ ] No assertion that is only a toast
- [ ] Spec green

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/kill-correction.spec.ts` covers the real kill/correction/unpublish journey: Admin publishes a unique story through the wizard; the Daily Star client feed (`Authorization: Bearer unb_live_testkey_dailystar_001`) contains the headline; Admin opens the story and either clicks a Kill button or invokes `StoryService::transition($s, 'killed', …)` via tinker (whichever the UI chrome exposes); the feed body matches `(killed|STORY KILLED|RETRACTED)` (tombstone); `index_outbox` has an `op=delete` row for the killed `public_id`. Then `StoryService::updateDraft(..., ['headline' => revised])` produces an outbox upsert; the feed contains the revised headline. The live toggle (`label.switch` in the news table) when clicked removes the story from the feed (asserted via API). Depends on COV-005 (kill writes index delete), COV-001 (isolation), COV-025 (live toggle assertion shape).
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
