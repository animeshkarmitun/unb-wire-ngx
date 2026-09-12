# Task: M12-NTF-005 — Notification center: mark-as-read, deep links, full page

**Status:** ✅ Complete
**Dependencies:** M12-NTF-001 (all event types wired and producing notifications)
**Parent ADR:** FR-NTF-002 (Must Have)

---

## 1. Contract (What)
- **Mark-as-read:**
  - Single: `POST /admin/notifications/{id}/read` → marks one notification read
  - All: `POST /admin/notifications/read-all` → marks all unread as read
  - Returns updated unread count
- **Deep links:** Clicking a notification navigates to:
  | Event | Target URL |
  |-------|-----------|
  | `review_requested`, `approved`, `changes_requested`, `published`, `killed` | `/admin/news/{language}/{storyId}` |
  | `handover` | `/admin/news/{language}/{storyId}` |
  | `note_added` | `/admin/news/{language}/{storyId}#notes` |
  | `media_approved`, `media_rejected`, `media_reedit` | `/admin/photos` (or batch link) |
- **Full notification page:** `GET /admin/notifications` — paginated list of all notifications, filterable by read/unread
- **Authorization:** Authenticated staff only; users can only read/mark their own notifications

---

## 2. Logic (How)

### 2.1 Livewire Component: `NotificationCenter`
1. Create `app/Livewire/Admin/NotificationCenter.php`:
   - Properties: `$notifications` (paginated), `$filter` ('all' | 'unread')
   - Methods: `markRead($id)`, `markAllRead()`, `getDeepLink($notification)`
   - Uses `Auth::user()->notifications()` with pagination (15 per page)
2. Create Blade view `resources/views/livewire/admin/notification-center.blade.php`:
   - Reuse topnav notification item styling
   - Add "Mark all as read" button
   - Each item is a clickable link (deep link)
   - Unread items have `bg-paper` highlight

### 2.2 Topnav Dropdown Enhancements
1. Wrap each notification item in `<a>` with deep link URL
2. Add "Mark all as read" link at top when unread > 0
3. Change "View distribution log" → "View all notifications" linking to `/admin/notifications`
4. On click, mark notification as read (inline `wire:click` or simple `fetch`)

### 2.3 Routes
- `GET /admin/notifications` → `NotificationCenter` Livewire component
- `POST /admin/notifications/{id}/read` → inline Livewire action (no separate controller needed)

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Livewire/Admin/NotificationCenter.php`
  - `resources/views/livewire/admin/notification-center.blade.php`
- **Files to Modify:**
  - `resources/views/components/topnav.blade.php` — add deep links, mark-as-read, "View all" link
  - `routes/web.php` — add `/admin/notifications` route
- **Reference Files:**
  - Existing topnav notification styling (lines 33-43)
  - `app/Models/User.php` (Notifiable trait)
- **Tests:**
  - `tests/Feature/NotificationCenterTest.php` — mark-read, mark-all-read, deep link resolution, pagination, auth guard

---

## 4. Prompt (For the Coding AI)
> You are the Coder. Implement task M12-NTF-005.
>
> 1. Read `resources/views/components/topnav.blade.php` (lines 24-45) for existing notification UI.
> 2. Create `app/Livewire/Admin/NotificationCenter.php` with:
>    - Paginated notifications list (15/page)
>    - `markRead($id)`: marks single notification, redirects to deep link
>    - `markAllRead()`: marks all unread
>    - `deepLink($notification)`: resolves URL from event data (story_id → `/admin/news/en/{id}`, batch_id → `/admin/photos`)
>    - `$filter` property: 'all' or 'unread'
> 3. Create `resources/views/livewire/admin/notification-center.blade.php` — full-page view matching admin chrome, reusing topnav item styling.
> 4. Update `topnav.blade.php`:
>    - Wrap each notification in `<a href="deep-link">` that also marks it read
>    - Add "Mark all read" button when unread > 0
>    - Change bottom link to "View all notifications" → `/admin/notifications`
> 5. Add route in `routes/web.php` under the admin middleware group.
> 6. Write `tests/Feature/NotificationCenterTest.php`:
>    - `test_mark_read_updates_notification`
>    - `test_mark_all_read`
>    - `test_deep_link_resolves_for_story_events`
>    - `test_deep_link_resolves_for_media_events`
>    - `test_requires_authentication`
>    - `test_pagination_works`
> 7. Run `php -l` on all files, then `php artisan test --filter=NotificationCenter`.

---

## 5. Test Criteria
- [ ] Single mark-as-read sets `read_at` timestamp
- [ ] Mark-all-read clears all unread for the user
- [ ] Deep link for story events resolves to `/admin/news/{lang}/{id}`
- [ ] Deep link for media events resolves to `/admin/photos`
- [ ] Notification center page requires auth
- [ ] Pagination returns 15 items per page
- [ ] Topnav dropdown items are clickable links
- [ ] Topnav "View all" links to `/admin/notifications`
- [ ] `php -l` clean on all modified files

---

## 6. Completion Notes
*(filled on completion)*

---

## 7. Prompt Ready?
- [x] Yes
