# Task: M10-CONC-001 — Editorial Concurrency, Soft Locking & Shift Handover

**Status:** ✅ Complete
**Dependencies:** M8-NEWS-001 (News List), M8-STORY-001 (Story Workflow), M12-NTF-001 (Notifications)

---

## 1. The Contract (What)

### Inputs & Actions
- `StoryService::acquireLock(Story $story, User $actor)`: Sets `locked_by = $actor->id`, `locked_at = now()`.
- `StoryService::takeOver(Story $story, User $actor, ?int $expectedVersion = null)`:
  - Validates optimistic version: throws `ConflictHttpException` if version mismatch.
  - In a DB transaction: updates `locked_by`, `locked_at`, `owner_id` to `$actor->id`.
  - Records immutable system note in `story_notes`: `"Taken over by {$actor->name} from {$prevName} — shift handover"`.
  - Records immutable audit event in `story_events` with `action: 'handover'`.
  - Dispatches handover notification to previous owner via `EditorialNotificationService::notifyHandover`.
- `NewsList::takeOver()`:
  - Calls `StoryService::takeOver($story, $user, $this->selectedVersion ?? $story->version)`.
  - Catches `ConflictHttpException` and displays user-friendly conflict banner: `"Version conflict — this story was updated by another user. Please reload."`.
  - Dispatches toast `"Ownership taken over successfully."`.
- `AddNews::takeOver()`:
  - Calls `StoryService::takeOver($s, auth()->user())`.
  - Updates local component `$this->ownerId`, `$this->ownerName`, `$this->ownerRole`.
  - Dispatches toast `"You have taken ownership of this story"`.

### UI Contracts
- `news-list.blade.php`:
  - When viewing story owned by another editor: displays owner card, on-shift badge, and `Take over` button.
  - Clicking `Take over` updates owner meta to `"You took over this story"`, hides button, and renders handover system note in thread.
  - Stale version trigger displays conflict error banner.
- `add-news-stepper.blade.php`:
  - When editing story owned by another editor: displays `#wfTakeOverBtn` (`Take over this story`) and green avatar.
  - Clicking `#wfTakeOverBtn` switches avatar to navy (current user) and removes takeover button.
- Notification Center / Topnav:
  - Previous owner receives handover notification with link to story.

---

## 2. The Logic (How)

1. **Seed Fixture (`seed-data.php concurrency`)**:
   - Creates a story owned by `Shohel Ahmed` (`shohel@unbnews.org`, Editor) with `locked_by = shohel->id`, `version = 1`.
2. **E2E Test 1: News List Sidebar Drawer Take-Over**:
   - `test@example.com` opens News List, selects the story drawer.
   - Asserts owner is `Shohel Ahmed (Editor)`.
   - Clicks `Take over` button.
   - Verifies toast, owner meta updates to `You took over this story`, handover system note appears.
   - Verifies DB `owner_id` and `locked_by` are now Test User's ID.
3. **E2E Test 2: Add News Wizard Soft Lock & Take-Over**:
   - `test@example.com` navigates to `/admin/add-news?id={storyId}` while story is owned by Shohel.
   - Asserts `#wfTakeOverBtn` is visible and owner is Shohel.
   - Clicks `#wfTakeOverBtn`.
   - Verifies toast, owner updates to `Test User`, `#wfTakeOverBtn` disappears.
4. **E2E Test 3: Optimistic Version Conflict (409) Handling**:
   - Story version in DB is updated behind the scenes.
   - User attempts takeover with stale version -> UI displays conflict error notice.
5. **E2E Test 4: Previous Owner Receives Handover Notification**:
   - After takeover, log in as `shohel@unbnews.org`.
   - Notification bell and `/admin/notifications` list contain handover notification.
6. **E2E Test 5: Handover Records Audit Trail**:
   - Audit Log Browser at `/admin/audit` records `handover` event from Shohel Ahmed to Test User.

---

## 3. The Context (Where)

- Target files:
  - `tests/e2e/helpers/seed-data.php`
  - `tests/e2e/editorial-concurrency-locking.spec.ts`
  - `app/Livewire/Admin/NewsList.php`
  - `app/Services/NotificationService.php`

---

## 4. Verification & Live Smoke

- `npx playwright test tests/e2e/editorial-concurrency-locking.spec.ts`: 5/5 passed (1.6m)
- `php scripts/schema-parity-check.php`: All schema parity checks PASSED
- `php artisan test`: 587 passed, 1 skipped (2143 assertions)
- Combined E2E suite (8 suites, 36 tests): 36 passed (3.3m)
- Live routes smoked: `/admin/news/en`, `/admin/add-news`, `/admin/notifications`, `/admin/audit`
