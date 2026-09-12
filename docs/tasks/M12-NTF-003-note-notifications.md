# Task: M12-NTF-003 — Wire note reply notifications into NoteService::add

**Status:** ✅ Complete
**Dependencies:** M12-NTF-001 (NotificationService pattern established)
**Parent ADR:** FR-NTF-001

---

## 1. Contract (What)
- **Inputs:** Story model + note author — already available in `NoteService::add`
- **Outputs:** In-app notification sent to **story owner + all prior note authors on the story** (excluding the current author)
- **Event:** `note_added`
- **Burst key:** `note:{storyId}` — collapses multiple notes on the same story within 5 minutes (NFR §7)
- **Authorization:** N/A (internal service call)

---

## 2. Logic (How)
1. In `NoteService::add`, after the audit log call (line ~47), resolve recipients:
   ```php
   $participants = $story->notes()
       ->where('user_id', '!=', $author->id)
       ->distinct('user_id')
       ->pluck('user_id');
   // Also include story owner if not the author
   if ($story->owner_id !== $author->id) {
       $participants->push($story->owner_id);
   }
   $users = User::whereIn('id', $participants->unique())->get();
   ```
2. Inject `NotificationService` into `NoteService` and call:
   ```php
   $this->notifications->notifyNoteAdded($story->id, $story->headline, $author->id, $users);
   ```
3. Add `notifyNoteAdded` method to `NotificationService`:
   ```php
   public function notifyNoteAdded(int $storyId, string $headline, int $actorId, $users): void
   {
       $this->burstNotify('note:'.$storyId, $users, 'note_added', [
           'story_id' => $storyId, 'headline' => $headline, 'actor_id' => $actorId,
       ]);
   }
   ```
4. Update topnav dot color: `note_added` → blue dot (already default).

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Services/NoteService.php` — inject NotificationService, add call after audit log
  - `app/Services/NotificationService.php` — add `notifyNoteAdded` method
- **Reference Files:**
  - `app/Models/StoryNote.php`, `app/Models/Story.php` (notes relationship)
- **Tests:**
  - `tests/Feature/NoteNotificationTest.php` — assert thread participants notified, author excluded, burst dedup collapses within 5 min

---

## 4. Prompt (For the Coding AI)
> You are the Coder. Implement task M12-NTF-003.
>
> 1. Read `app/Services/NoteService.php`, `app/Services/NotificationService.php`.
> 2. Inject `NotificationService` into `NoteService::__construct`.
> 3. After the audit log call in `NoteService::add` (~line 47), resolve recipients: all distinct `user_id` from prior notes on the story + story owner, minus the current author.
> 4. Call `$this->notifications->notifyNoteAdded(...)`.
> 5. Add `notifyNoteAdded` method to `NotificationService` with burst key `'note:'.$storyId`.
> 6. Write `tests/Feature/NoteNotificationTest.php`:
>    - `test_note_notifies_story_owner`
>    - `test_note_notifies_prior_participants`
>    - `test_note_does_not_notify_author`
>    - `test_note_burst_dedup_within_5_minutes`
> 7. Run `php -l` on all changed files, then `php artisan test --filter=NoteNotification`.

---

## 5. Test Criteria
- [ ] Story owner receives `note_added` notification (if not the author)
- [ ] Prior note authors on the story receive notification
- [ ] Note author does not receive self-notification
- [ ] Burst dedup collapses within 5-min window per story
- [ ] Notification data contains `story_id`, `headline`, `actor_id`
- [ ] `php -l` clean on all modified files
- [ ] Existing NoteService tests still pass

---

## 6. Completion Notes
*(filled on completion)*

---

## 7. Prompt Ready?
- [x] Yes
