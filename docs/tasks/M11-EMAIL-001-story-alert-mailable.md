# Task: M11-EMAIL-001 — StoryAlert Mailable + Blade template

**Status:** ⏳ Pending
**Dependencies:** M11-HOOK-001 (WebhookPayloadBuilder for story data shape)
**Parent ADR:** FR-DST-002

---

## 1. Contract (What)
- **Inputs:** Story model (with category, tags, media), alert type string, client name
- **Outputs:** Formatted HTML email ready for sending
- **Email structure:**
  - Subject: `[UNB Wire] Breaking: {headline}` or `[UNB Wire] {headline}`
  - Header: UNB Wire logo + alert badge (Breaking/Exclusive/Media Pack)
  - Body: headline, summary (brief), category, published_at, author
  - Media: thumbnail URLs (if any)
  - Footer: "You are receiving this because {client_name} subscribes to UNB Wire alerts."
  - Unsubscribe note: "Contact your account manager to update preferences."

---

## 2. Logic (How)
1. Create `app/Mail/StoryAlert.php` Mailable.
2. Create Blade template `resources/views/mail/story-alert.blade.php`.
3. Mailable accepts `Story $story`, `string $clientName`, `bool $isBreaking`.
4. Uses `envelope()` for subject, `content()` for view, `attachments()` empty.
5. Template uses inline CSS (email-safe) — no Tailwind.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Mail/StoryAlert.php`
  - `resources/views/mail/story-alert.blade.php`
- **Tests:**
  - `tests/Feature/StoryAlertMailableTest.php` — assert Mailable renders, subject format, content includes headline/category

---

## 4. Prompt (For the Coding AI)
> Create `app/Mail/StoryAlert.php` Mailable and `resources/views/mail/story-alert.blade.php` template. The Mailable takes a Story, client name, and isBreaking flag. Subject: "[UNB Wire] Breaking: {headline}" or "[UNB Wire] {headline}". Template: clean HTML email with headline, summary, category, published_at, media thumbnails, footer with client name. Use inline CSS (no Tailwind). Write tests with Mail::fake() and Mailable assertions.

---

## 5. Test Criteria
- [ ] Mailable renders without errors
- [ ] Subject includes "[UNB Wire]" prefix
- [ ] Breaking stories get "Breaking:" in subject
- [ ] Content includes headline, summary, category
- [ ] `php -l` clean

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
