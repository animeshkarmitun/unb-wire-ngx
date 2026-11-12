# Task: M17-PORTAL-NTF-001 — Client Portal Notification Center

**Status:** ✅ Completed
**Dependencies:** M12-PROFILE-003 (portal session auth)
**Parent ADR:** FR-PRT-002 (home feed), FR-NTF-002 (notification center), DEC-007

---

## 1. Contract (What)
- **Inputs & Endpoints:**
  - `GET /api/v1/portal/notifications?filter={all|unread}&page={page}` — lists notifications for authenticated `client_user` with pagination and `unread_count`.
  - `PATCH /api/v1/portal/notifications/{id}/read` — marks a single notification as read.
  - `POST /api/v1/portal/notifications/mark-all-read` — marks all unread notifications for this user as read.
  - `DELETE /api/v1/portal/notifications/{id}` — permanently deletes a single notification for this user.
  - `DELETE /api/v1/portal/notifications` — clears all notifications (or all read notifications) for this user.
- **Outputs:**
  - Notification items containing:
    ```json
    {
      "id": "uuid",
      "type": "story.breaking|story.published|story.updated|story.killed|system.notice",
      "title": "Headline or Alert Title",
      "message": "Summary or note",
      "public_id": "01M...",
      "story_id": 123,
      "category": "National",
      "urgency": "breaking|urgent|regular",
      "read_at": "2026-11-12T10:00:00Z" | null,
      "created_at": "2026-11-12T10:00:00Z"
    }
    ```
  - Unread count: `{ unread_count: int, data: [...], meta: {...} }`
- **Authorization:** `EnsurePortalSession` (`portal.session` middleware). Multi-tenant isolation: client users can only view, mark as read, and delete their own notifications.

---

## 2. Logic (How)
1. Add `Illuminate\Notifications\Notifiable` trait to `App\Models\ClientUser`.
2. Create notification class `App\Notifications\ClientStoryNotification` which stores payload into the database `notifications` table.
3. Hook `ClientStoryNotification` in `App\Jobs\FanoutStory` when publishing stories (especially breaking/urgent) and when killing stories (`story.killed`).
4. Create `App\Http\Controllers\Api\PortalNotificationController` with methods: `index`, `markAsRead`, `markAllRead`, `destroy`, `destroyAll`.
5. Register routes in `routes/api.php` under `portal.session` middleware group.
6. Build `portal/app/notifications/page.tsx`:
   - Masthead with back link, UNB brand mark, and clock.
   - Filters: "All Notifications" vs "Unread".
   - Actions: "Mark all as read" button and "Clear all" button.
   - Notification item list with badges (🚨 Breaking, ⚠️ Killed, 🔄 Updated, 📰 Published).
   - Click to open story (`/story/[public_id]`).
   - Action buttons: "Mark as read" & "Delete".
7. Update `portal/app/page.tsx` (and `portal/app/account/page.tsx`):
   - Add topnav Bell Icon 🔔 with real-time unread badge counter.
   - Add "Notifications" link in user dropdown menu.
8. Write comprehensive tests in `tests/Feature/Api/PortalNotificationTest.php`.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Notifications/ClientStoryNotification.php`
  - `app/Http/Controllers/Api/PortalNotificationController.php`
  - `portal/app/notifications/page.tsx`
  - `tests/Feature/Api/PortalNotificationTest.php`
- **Files to Modify:**
  - `app/Models/ClientUser.php` (add `Notifiable`)
  - `routes/api.php` (add `/v1/portal/notifications` routes)
  - `app/Jobs/FanoutStory.php` (notify client users on publish/kill)
  - `portal/app/page.tsx` (add Bell icon & user dropdown link)

---

## 4. Test Criteria
- [ ] Feature tests pass (`PortalNotificationTest.php`): auth, listing, mark read, mark all read, delete, isolation.
- [ ] Schema parity check passes.
- [ ] TypeScript/Next.js compiles without errors (`npm run build` in `portal/`).
- [ ] Synced to remote staging server `wire-app-1` and live verified.
