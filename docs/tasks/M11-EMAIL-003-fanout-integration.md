# Task: M11-EMAIL-003 — Integrate email delivery into FanoutStory

**Status:** ⏳ Pending
**Dependencies:** M11-EMAIL-002
**Parent ADR:** FR-DST-002

---

## 1. Contract (What)
- **Inputs:** Published Story via FanoutStory job
- **Outputs:** `SendStoryEmail` dispatched for each client that has email enabled
- **Integration point:** After webhook + FTP fan-out loop in FanoutStory

---

## 2. Logic (How)
1. In `FanoutStory::handle()`, after the existing per-channel loop:
   - Query all clients that have `notes` containing email config with `on = true`.
   - For each matched client: `SendStoryEmail::dispatch($story, $client)`.
2. **Alternative simpler approach:** Inside the existing client loop (which already iterates matched clients), check if `$client->notes['channels']['email']['on']` is true and dispatch `SendStoryEmail`.
3. Email doesn't go through `client_channels` / `deliveries` table — it uses `clients.notes` directly. This is consistent with existing UI architecture.

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Jobs/FanoutStory.php` (add email dispatch after channel loop)
- **Reference:**
  - `app/Jobs/SendStoryEmail.php` (M11-EMAIL-002)
  - `app/Models/Client.php` (notes accessor)
- **Tests:**
  - Update `tests/Feature/FanoutAdvancedTest.php` or create `tests/Feature/EmailFanoutTest.php` — Queue::fake() + assert SendStoryEmail dispatched for email-enabled clients

---

## 4. Prompt (For the Coding AI)
> Integrate email into FanoutStory. After the existing channel loop, query clients with email enabled in notes JSON, dispatch SendStoryEmail for each. Write test with Queue::fake() asserting email job dispatched for clients with email on, and NOT dispatched for clients with email off.

---

## 5. Test Criteria
- [ ] Publishing a story dispatches `SendStoryEmail` for email-enabled clients
- [ ] Clients without email config are skipped
- [ ] Clients with `email.on = false` are skipped
- [ ] Existing webhook/FTP tests unaffected
- [ ] `php -l` clean

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
