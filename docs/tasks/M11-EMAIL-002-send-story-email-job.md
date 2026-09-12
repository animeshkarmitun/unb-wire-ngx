# Task: M11-EMAIL-002 — SendStoryEmail job + alert preference matching

**Status:** ⏳ Pending
**Dependencies:** M11-EMAIL-001
**Parent ADR:** FR-DST-002

---

## 1. Contract (What)
- **Inputs:** Story model, Client model
- **Outputs:** Email sent to all recipients in `client.notes['channels']['email']['list']`
- **Alert matching:** Only send if story matches at least one of the client's alert preferences:
  - `alerts.breaking` → story.is_breaking
  - `alerts.media_pack` → story.media.isNotEmpty()
  - `alerts.exclusive` → story.is_exclusive
  - If all alert prefs are false → send all stories (backward compat, same as TriggerMatcher)
- **Guard:** Skip if `notes['channels']['email']['on']` is false/missing

---

## 2. Logic (How)
1. Create `app/Jobs/SendStoryEmail.php` queue job.
2. `handle()`:
   - Load client's email config from `$client->notes['channels']['email']`.
   - If `!$config['on']` → return early.
   - Check alert preferences against story (reuse `TriggerMatcher::shouldFire()` logic with `$config['alerts']`).
   - If no match → return early.
   - Get recipients from `$config['list']` (array of email strings).
   - For each recipient: `Mail::to($email)->queue(new StoryAlert($story, $client->name, $story->is_breaking))`.
3. On failure: update `notes['channels']['email']['health'] = 'fail'`.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Jobs/SendStoryEmail.php`
- **Reference:**
  - `app/Services/Delivery/TriggerMatcher.php` (reuse alert matching logic)
  - `app/Mail/StoryAlert.php` (M11-EMAIL-001)
  - `app/Services/ClientService.php` lines 254-265 (how email config is stored in notes)
- **Tests:**
  - `tests/Feature/SendStoryEmailTest.php` — Mail::fake(), assert emails sent to correct recipients, alert matching, skip when off

---

## 4. Prompt (For the Coding AI)
> Create `app/Jobs/SendStoryEmail.php`. Read email config from `$client->notes['channels']['email']`. Check 'on' flag, match alert preferences (breaking/media_pack/exclusive) against story — reuse TriggerMatcher logic. Send StoryAlert Mailable to each recipient in 'list' array. Write tests with Mail::fake() asserting correct recipient targeting, alert filtering, and skip-when-off behavior.

---

## 5. Test Criteria
- [ ] Emails sent to all recipients in list when alerts match
- [ ] No email when `notes.channels.email.on` is false
- [ ] Breaking alert preference only sends for breaking stories
- [ ] No alert prefs configured → sends for all stories (backward compat)
- [ ] `Mail::fake()` captures all sent Mailables
- [ ] `php -l` clean

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
