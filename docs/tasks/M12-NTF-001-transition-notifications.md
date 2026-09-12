# Task: M12-NTF-001 — Wire editorial notifications into StoryService::transition

**Status:** ✅ Complete
**Dependencies:** None (plumbing exists: NotificationService, StoryNotification, notifications table)
**Parent ADR:** FR-NTF-001 (Must Have)

---

## 1. Contract (What)
- **Inputs:** Story model + target status + actor (already available inside `StoryService::transition`)
- **Outputs:** In-app database notifications dispatched to correct recipients via `NotificationService`
- **Events to wire:**
  | Transition (`$to`) | Event name | Recipient |
  |---------------------|-----------|-----------|
  | `approved` | `approved` | Story owner (reporter) |
  | `changes_requested` | `changes_requested` | Story owner (reporter) |
  | `killed` | `killed` | Story owner (reporter) |
  | `in_review` | `review_requested` | Editors + Admins |
- **Authorization:** N/A (internal service call, already authorized by caller)

---

## 2. Logic (How)
1. Inject `NotificationService` into `StoryService` constructor.
2. Inside `transition()`, after the `story_events` + audit log block (line ~170), add notification dispatch:
   - `in_review` → call `notifyReviewRequested($story->id, $story->headline, $actor->id)` (centralizes the call currently in `AddNews::sendToReview`)
   - `approved` → call `notifyStatusChange($story->id, 'approved', $actor->id)`
   - `changes_requested` → call `notifyStatusChange($story->id, 'changes_requested', $actor->id)`
   - `killed` → call `notifyStatusChange($story->id, 'killed', $actor->id)`
3. Remove the duplicate `notifyReviewRequested` / `notifyStatusChange` calls from `AddNews` Livewire component (they should live in the service, not the UI layer).
4. Add `headline` to the `notifyStatusChange` data payload so the topnav dropdown can display it.

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Services/StoryService.php` — inject NotificationService, add dispatch calls inside `transition()`
  - `app/Services/NotificationService.php` — add `headline` to `notifyStatusChange` data payload
  - `app/Livewire/Admin/AddNews.php` — remove direct NotificationService calls (now centralized)
- **Reference Files:**
  - `app/Notifications/StoryNotification.php`
  - `resources/views/components/topnav.blade.php` (event name → dot color mapping)
- **Tests:**
  - `tests/Feature/TransitionNotificationTest.php` — assert notifications created for each transition type with correct recipients

---

## 4. Prompt (For the Coding AI)
> You are the Coder. Implement task M12-NTF-001.
>
> **Goal:** Centralize editorial notification dispatch in `StoryService::transition()` so every status change — regardless of which UI triggers it (AddNews wizard, NewsList drawer, future API) — notifies the right people.
>
> 1. Read `app/Services/StoryService.php`, `app/Services/NotificationService.php`, `app/Livewire/Admin/AddNews.php`.
> 2. Inject `NotificationService` into `StoryService::__construct`.
> 3. Inside `transition()` after the audit log call (~line 170), add a match on `$to`:
>    - `'in_review'` → `$this->notifications->notifyReviewRequested($story->id, $story->headline, $actor->id)`
>    - `'approved'`, `'changes_requested'`, `'killed'` → `$this->notifications->notifyStatusChange($story->id, $to, $actor->id)`
> 4. Update `NotificationService::notifyStatusChange` to accept an optional `$headline` parameter and include it in the data array.
> 5. Remove the `notifyReviewRequested` and `notifyStatusChange` calls from `AddNews.php` (they are now handled by the service).
> 6. Write `tests/Feature/TransitionNotificationTest.php`:
>    - `test_approved_notifies_story_owner`
>    - `test_changes_requested_notifies_story_owner`
>    - `test_killed_notifies_story_owner`
>    - `test_in_review_notifies_editors`
>    - Use `Notification::fake()` and `Notification::assertSentTo()`.
> 7. Run `php -l` on all changed files, then `php artisan test --filter=TransitionNotification`.

---

## 5. Test Criteria
- [ ] `approved` transition creates notification for story owner
- [ ] `changes_requested` transition creates notification for story owner
- [ ] `killed` transition creates notification for story owner
- [ ] `in_review` transition creates notification for Editors/Admins
- [ ] `AddNews` no longer calls NotificationService directly
- [ ] Topnav dropdown renders new event types correctly
- [ ] Burst dedup still works (5-min window per story+action)
- [ ] `php -l` clean on all modified files
- [ ] Existing tests still pass

---

## 6. Completion Notes
*(filled on completion)*

---

## 7. Prompt Ready?
- [x] Yes
