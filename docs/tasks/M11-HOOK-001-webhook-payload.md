# Task: M11-HOOK-001 — Enrich webhook payload (full story + event type)

**Status:** ⏳ Pending
**Dependencies:** FIX-001, FIX-002
**Parent ADR:** FR-DST-002, `docs/audit-remaining-todo.md` Phase B

---

## 1. Contract (What)
- **Inputs:** Published `Story` model (with category, tags, media eager-loaded) + event type string
- **Outputs:** Webhook JSON payload matching wire service conventions:
  ```json
  {
    "event": "story.published|story.updated|story.killed|story.correction",
    "timestamp": "2026-09-12T22:00:00Z",
    "data": {
      "public_id": "UNB-2026-xxxxx",
      "headline": "...",
      "summary": "...",
      "body_html": "...",
      "language": "en",
      "category": {"slug": "politics", "name": "Politics"},
      "tags": ["election", "dhaka"],
      "author": "Staff Reporter",
      "is_breaking": false,
      "published_at": "2026-09-12T22:00:00Z",
      "media": [{"public_id": "...", "kind": "photo", "caption": "...", "download_url": "/api/v1/media/{id}/download"}]
    }
  }
  ```
- **Authorization:** N/A (internal job)

---

## 2. Logic (How)
1. Create `App\Services\Delivery\WebhookPayloadBuilder` service class.
2. Method `build(Story $story, string $event): array` — eager-loads category, tags, media; formats payload.
3. Update `FanoutStory.php` line 77 to use `WebhookPayloadBuilder::build($story, 'story.published')` instead of inline `['public_id' => ..., 'headline' => ...]`.
4. For kills/corrections, caller passes appropriate event type.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Services/Delivery/WebhookPayloadBuilder.php`
- **Files to Modify:**
  - `app/Jobs/FanoutStory.php` (line 77 — replace inline payload)
- **Reference Files:**
  - `app/Models/Story.php`, `app/Models/MediaAsset.php`
- **Tests to Create:**
  - `tests/Feature/WebhookPayloadTest.php` — assert full payload shape, event types, media URLs

---

## 4. Prompt (For the Coding AI)
> You are the Coder. Implement task M11-HOOK-001.
> Create `app/Services/Delivery/WebhookPayloadBuilder.php` with a `build(Story $story, string $event): array` method that produces the full webhook payload shape defined in the Contract section above.
> Update `app/Jobs/FanoutStory.php` to use this builder instead of the inline `['public_id' => ..., 'headline' => ...]` on line 77.
> Write feature tests in `tests/Feature/WebhookPayloadTest.php` asserting payload shape for `story.published`, `story.killed`, and media inclusion.
> Run `php -l` on all changed files, then `php artisan test --filter=WebhookPayload` and `php artisan test --filter=FanoutAdvancedTest`.

---

## 5. Test Criteria
- [ ] Payload contains all required fields (event, timestamp, data.headline, data.body_html, data.category, data.tags, data.media)
- [ ] Event type correctly passed for published/killed/correction
- [ ] Media array includes download_url for each attached asset
- [ ] `FanoutAdvancedTest` still passes (5 tests, 7 assertions)
- [ ] `php -l` clean on all modified files

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
