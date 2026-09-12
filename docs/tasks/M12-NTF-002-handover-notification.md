# Task: M12-NTF-002 — Wire takeover notification into StoryService::takeOver

**Status:** ✅ Complete
**Dependencies:** M12-NTF-001 (NotificationService injected into StoryService)
**Parent ADR:** FR-NWS-013 (Handover), FR-NTF-001

---

## 1. Contract (What)
- **Inputs:** Story model + actor (new owner) — already available in `StoryService::takeOver`
- **Outputs:** In-app notification sent to the **previous owner** informing them of the handover
- **Event:** `handover` — previous owner receives notification with actor name and story headline
- **Authorization:** N/A (internal service call)

---

## 2. Logic (How)
1. In `StoryService::takeOver`, after the audit log call (line ~115), call:
   ```php
   if ($prev && $prev->id !== $actor->id) {
       $this->notifications->notifyHandover($story->id, $story->headline, $actor->id, $prev);
   }
   ```
2. Add `notifyHandover` method to `NotificationService`:
   ```php
   public function notifyHandover(int $storyId, string $headline, int $actorId, User $prevOwner): void
   {
       $this->burstNotify('handover:'.$storyId, collect([$prevOwner]), 'handover', [
           'story_id' => $storyId, 'headline' => $headline, 'actor_id' => $actorId,
       ]);
   }
   ```
3. Update topnav dot color map: `handover` → orange/amber dot.

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Services/StoryService.php` — add notification call in `takeOver()` (~line 115)
  - `app/Services/NotificationService.php` — add `notifyHandover` method
  - `resources/views/components/topnav.blade.php` — add `handover` to event color map
- **Tests:**
  - `tests/Feature/HandoverNotificationTest.php` — assert previous owner receives `handover` notification, actor does not self-notify

---

## 4. Prompt (For the Coding AI)
> You are the Coder. Implement task M12-NTF-002.
>
> 1. Read `app/Services/StoryService.php` (lines 88-117), `app/Services/NotificationService.php`.
> 2. Add `notifyHandover(int $storyId, string $headline, int $actorId, User $prevOwner)` to `NotificationService`.
> 3. In `StoryService::takeOver`, after the audit log (line ~115, inside the DB::transaction), call `notifyHandover` only if `$prev` exists and is not the same as `$actor`.
> 4. In `topnav.blade.php` line 35, update the event color ternary to include `'handover' => 'bg-amber'`.
> 5. Write `tests/Feature/HandoverNotificationTest.php`:
>    - `test_takeover_notifies_previous_owner`
>    - `test_takeover_does_not_self_notify`
>    - Use `Notification::fake()`.
> 6. Run `php -l` on all changed files, then `php artisan test --filter=HandoverNotification`.

---

## 5. Test Criteria
- [ ] Previous owner receives `handover` notification with story headline
- [ ] Actor does not receive self-notification when taking over their own story
- [ ] Notification data contains `story_id`, `headline`, `actor_id`
- [ ] Burst dedup works (5-min window per `handover:{storyId}`)
- [ ] Topnav renders amber dot for `handover` events
- [ ] `php -l` clean on all modified files

---

## 6. Completion Notes
*(filled on completion)*

---

## 7. Prompt Ready?
- [x] Yes
