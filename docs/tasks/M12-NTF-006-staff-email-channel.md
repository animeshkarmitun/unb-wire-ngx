# Task: M12-NTF-006 — Staff email channel for editorial notifications

**Status:** ✅ Complete
**Dependencies:** M12-NTF-001 (all transition notifications centralized), M12-NTF-005 (notification center live)
**Parent ADR:** FR-NTF-001 (email delivery), NFR §7 (burst-collapse applies to email too)

---

## 1. Contract (What)
- **Goal:** Add `mail` channel to `StoryNotification` so staff receive email alerts for critical editorial events
- **Email-worthy events** (not all events need email):
  | Event | Email? | Rationale |
  |-------|--------|-----------|
  | `review_requested` | ✅ | Editor needs to act |
  | `approved` | ❌ | Low urgency, in-app sufficient |
  | `changes_requested` | ✅ | Reporter needs to act |
  | `published` | ❌ | In-app sufficient |
  | `killed` | ✅ | Urgent, reporter must know |
  | `handover` | ✅ | Previous owner should know |
  | `note_added` | ❌ | Too noisy for email; in-app only |
  | `media_*` | ❌ | In-app sufficient |
- **Burst dedup:** The existing 5-min Redis burst cache already prevents email spam — `burstNotify` gates both channels
- **Email format:** Simple text/HTML email with event description, story headline, actor name, and link to the story

---

## 2. Logic (How)
1. Update `StoryNotification::via()` to conditionally include `'mail'`:
   ```php
   public function via(object $notifiable): array
   {
       $channels = ['database'];
       if (in_array($this->event, ['review_requested', 'changes_requested', 'killed', 'handover'])) {
           $channels[] = 'mail';
       }
       return $channels;
   }
   ```
2. Add `toMail()` method to `StoryNotification`:
   ```php
   public function toMail(object $notifiable): MailMessage
   {
       $subject = match ($this->event) {
           'review_requested' => '[UNB Wire] Story awaiting review: ' . ($this->data['headline'] ?? ''),
           'changes_requested' => '[UNB Wire] Changes requested: ' . ($this->data['headline'] ?? ''),
           'killed' => '[UNB Wire] Story killed: ' . ($this->data['headline'] ?? ''),
           'handover' => '[UNB Wire] Story ownership transferred: ' . ($this->data['headline'] ?? ''),
           default => '[UNB Wire] Notification',
       };
       return (new MailMessage)->subject($subject)->...;
   }
   ```
3. No new Blade template needed — use Laravel's built-in `MailMessage` builder (simple, maintainable).

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Notifications/StoryNotification.php` — add conditional `mail` channel + `toMail()` method
- **Reference Files:**
  - `app/Mail/StoryAlert.php` (client email — different from staff email, but reference for style)
- **Tests:**
  - `tests/Feature/StaffEmailNotificationTest.php` — assert email sent for email-worthy events, not sent for non-email events

---

## 4. Prompt (For the Coding AI)
> You are the Coder. Implement task M12-NTF-006.
>
> 1. Read `app/Notifications/StoryNotification.php`.
> 2. Update `via()` to include `'mail'` only for events: `review_requested`, `changes_requested`, `killed`, `handover`.
> 3. Add `toMail(object $notifiable): MailMessage` with appropriate subject lines and body content (headline, event description, link to story).
> 4. Write `tests/Feature/StaffEmailNotificationTest.php`:
>    - `test_review_requested_sends_email`
>    - `test_changes_requested_sends_email`
>    - `test_killed_sends_email`
>    - `test_handover_sends_email`
>    - `test_approved_does_not_send_email`
>    - `test_published_does_not_send_email`
>    - `test_note_added_does_not_send_email`
>    - Use `Notification::fake()` + `Notification::assertSentTo($user, StoryNotification::class, fn ($n) => in_array('mail', $n->via($user)))`.
> 5. Run `php -l`, then `php artisan test --filter=StaffEmailNotification`.

---

## 5. Test Criteria
- [ ] `review_requested` sends email to editors
- [ ] `changes_requested` sends email to story owner
- [ ] `killed` sends email to story owner
- [ ] `handover` sends email to previous owner
- [ ] `approved`, `published`, `note_added`, `media_*` do NOT trigger email
- [ ] Email subject includes `[UNB Wire]` prefix and headline
- [ ] Burst dedup still works (email + database both gated by same `burstNotify`)
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

---

## 7. Prompt Ready?
- [x] Yes
