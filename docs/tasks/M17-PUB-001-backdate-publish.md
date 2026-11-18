# Task: M17-PUB-001 — Backdate Story Publishing Support

**Status:** ✅ Completed
**Dependencies:** None
**Parent ADR:** Editorial lifecycle invariants (AGENTS.md §2.1, DEC-007)

---

## 1. Contract (What)
- **Inputs / Validation:**
  - `isBackdated`: bool, default `false`.
  - `backdatedAt`: optional datetime-local string (Asia/Dhaka time).
  - Validation: If `isBackdated` is true, `backdatedAt` is required, must parse to a valid datetime in `Asia/Dhaka`, and must be in the past (`<= now()`). Future datetimes are rejected with an explicit error directing to Embargo (`embargo_until`).
- **Outputs / State:**
  - `stories.published_at` is set to the provided backdated timestamp (stored as UTC `timestamptz`).
  - `story_events` audit record captures `payload.is_backdated = true` and `payload.backdated_to = <ISO8601>`, while the event `created_at` records the real-time moment of publishing.
  - Wire feeds, Meilisearch indexes, and portal feeds sort chronologically according to the backdated `published_at`.
- **Authorization:**
  - Gated server-side by `RbacService.assertCan(user, 'stories', 'publish')`.

---

## 2. Logic (How)
1. **Service Layer (`StoryService`):**
   - Update `transition(Story $story, string $to, User $actor, string $gate = 'manual', string $aiGate = 'manual', ?Carbon $customPublishedAt = null): Story`.
   - When `$to === 'published'`:
     - If `$customPublishedAt` is provided:
       - Assert `$customPublishedAt->lte(now())`, otherwise throw `UnprocessableEntityHttpException('Backdate publication timestamp cannot be in the future. Use embargo for future scheduling.')`.
       - Assign `$extra['published_at'] = $customPublishedAt`.
       - Enrich `story_events` payload with `is_backdated => true, backdated_to => $customPublishedAt->toIso8601String(), published_at_wallclock => now()->toIso8601String()`.
       - Enrich audit log data with backdate metadata.
     - Else fallback to existing behavior: if `! $story->published_at`, set `$extra['published_at'] = now()`.
2. **Livewire Component (`AddNews`):**
   - Add properties: `public bool $isBackdated = false;` and `public ?string $backdatedAt = null;`.
   - In `publish()`:
     - If `$this->isBackdated`:
       - Validate `backdatedAt` presence and past date (`Asia/Dhaka` timezone).
       - Parse to UTC Carbon instance and pass to `StoryService::transition($s, 'published', auth()->user(), 'manual', 'manual', $customPublishedAt)`.
3. **UI Integration (`add-news-step4-review.blade.php`):**
   - Add a "Publication Timing" block in Step 4 Review:
     - Toggle/checkbox for Backdate publication.
     - Datetime picker (`datetime-local`) with `max` bounded to current Dhaka time.
     - Visual badge indicating backdated publication date.
4. **Feeds & Search Sync:**
   - Existing Meilisearch and FeedCache flows already consume `story.published_at`, so backdated stories will naturally take their historical chronological placement in search and feeds.

---

## 3. Context (Where)
- **Modify:**
  - `app/Services/StoryService.php`
  - `app/Livewire/Admin/AddNews.php`
  - `resources/views/livewire/admin/partials/add-news-step4-review.blade.php`
  - `tests/Feature/StoryServiceTest.php` (or new dedicated test file)
- **Reference:**
  - `AGENTS.md` §2.1 (Editorial Lifecycle)
  - `App\Services\RbacService`
  - `App\Support\FeedCache`

---

## 4. Test Criteria
- [x] Unit/Feature: Publishing with custom past `published_at` sets `stories.published_at` to the past timestamp.
- [x] Unit/Feature: Attempting to backdate with a future timestamp throws 422 exception.
- [x] Unit/Feature: Default publishing (without backdate) sets `published_at` to current time (`now()`).
- [x] Unit/Feature: `story_events` records backdate metadata in `payload`.
- [x] Unit/Feature: Livewire `AddNews` publish validates past date and publishes story with backdate.
- [x] `php -l` passes on all modified PHP files.
- [x] `php artisan test` passes.

---

## 5. Completion Notes
- **Shipped:**
  - `StoryService::transition`: added optional `?Carbon $customPublishedAt = null` parameter; validates past timestamp; sets `stories.published_at`; audits in `story_events.payload` (`is_backdated`, `backdated_to`, `published_at_wallclock`) and `audit_logs`.
  - `AddNews`: added `isBackdated` and `backdatedAt` properties; validated in `publish()` against Dhaka timezone and future dates; passes UTC Carbon to `StoryService`.
  - `add-news-step4-review.blade.php`: added Backdate toggle and datetime picker on Step 4 review panel, with live Dhaka timezone parsing and indicator badge.
- **Tests:**
  - Added unit tests in `StoryWorkflowTest.php` for backdated publish and future rejection.
  - Added feature tests in `AddNewsTest.php` for Livewire wizard backdating flow and future rejection.
  - All tests passing.
- **Parity:**
  - `php scripts/schema-parity-check.php` all checks passed.
