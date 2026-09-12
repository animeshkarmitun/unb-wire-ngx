# Task: M11-PORTAL-004 — Real-time live ticker via Reverb WebSocket

**Status:** ⏳ Pending
**Dependencies:** M11-PORTAL-002 (auth)
**Parent ADR:** NFR §14 real-time, `app-data/` prototype live ticker

---

## 1. Contract (What)
- **Inputs:** Reverb WebSocket connection on channel `wire.{language}`
- **Outputs:** Real-time story push notifications in portal live ticker bar
- **Current state:** Simulated via `setTimeout(18000)` injecting static fake stories

---

## 2. Logic (How)
1. **Laravel side:** Create broadcast event `App\Events\StoryPublished`:
   - Implements `ShouldBroadcast`.
   - Broadcasts on channel `wire.{language}` (public channel).
   - Payload: `{public_id, headline, summary, category, is_breaking, published_at}`.
2. Dispatch `StoryPublished` in `StoryWorkflowService` after successful publish transition.
3. **Portal side:**
   - Install `laravel-echo` + `pusher-js` in Next.js portal.
   - Create `portal/lib/echo.ts` — configure Echo with Reverb connection (env: `NEXT_PUBLIC_REVERB_*`).
   - Update `portal/app/page.tsx`:
     - Replace `setTimeout(18000)` simulated ticker with Echo listener on `wire.en` / `wire.bn`.
     - On event: prepend story to `pendingNew` state → show live bar notification.
4. Update live indicator from static "checking every 30s" to actual WebSocket connection status.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Events/StoryPublished.php`
  - `portal/lib/echo.ts`
- **Files to Modify:**
  - `app/Services/StoryWorkflowService.php` (dispatch broadcast event on publish)
  - `portal/app/page.tsx` (replace setTimeout ticker, add Echo listener)
  - `portal/package.json` (add `laravel-echo`, `pusher-js`)
- **Reference:**
  - `config/broadcasting.php` (Reverb config already present)
  - `.env.example` lines 71–76 (REVERB_* config)
  - `routes/channels.php`
- **Tests:**
  - `tests/Feature/StoryPublishedBroadcastTest.php` — assert event broadcasts on publish
  - Update `tests/e2e/portal-ui.spec.ts` — verify live ticker receives events

---

## 4. Prompt (For the Coding AI)
> Implement M11-PORTAL-004. Create `StoryPublished` broadcast event. Dispatch on story publish in StoryWorkflowService. Set up Laravel Echo in Next.js portal. Replace fake setTimeout ticker with real WebSocket listener on `wire.{language}` channel. Update live indicator to reflect connection status. Write broadcast test and update E2E.

---

## 5. Test Criteria
- [ ] Publishing a story broadcasts `StoryPublished` event
- [ ] Event payload contains headline, category, is_breaking, published_at
- [ ] Portal Echo listener receives events and shows live bar
- [ ] Simulated `setTimeout` removed
- [ ] Live indicator reflects WebSocket connection status
- [ ] `php -l` clean, existing tests pass

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
