# Task: COS-FTP-MGMT — Client-managed FTP channel from the client portal

**Status:** 🔧 In Progress
**Priority:** Feature (owner-approved design)
**Affected surfaces:** `client_users` schema, portal API (new FTP endpoints), admin Clients page (flag toggle), Next.js portal (new /ftp page), notifications
**Parent contracts:** DEC-007 (one entitlement definition), COS-30 (honest states), FR-CLT-002 (portal accounts)

---

## 1. Owner-approved decisions

| # | Decision | Owner's call |
|---|---|---|
| 1 | Password policy | **Immediate live**; Test-connection available (test before save) |
| 2 | Channels per client | **Exactly one** FTP channel |
| 3 | Client-editable fields | **host, port, username, password, protocol (FTP/FTPS/SFTP)**; `wire_format` stays staff-set. *Remote path not exposed — delivery writes to root `/` (factory ignores path); noted honestly* |
| 4 | Who can manage | **The client's admin portal user only** → new `client_users.can_manage_delivery` flag (staff-toggled on the admin Clients page; migration backfills the first active user per client) |
| 5 | Staff informed | **Yes** — staff with `clients,edit` get a notification + every change is audit-logged (`actor_type: client_user`) |
| 6 | Protocol | **FTP / FTPS / SFTP** (factory supports all three) |

## 2. Design

### Backend (routes `v1/portal/ftp`, `portal.session` + `throttle:portal-session`)
- `GET /ftp` — the client's FTP channel (host, port, username, protocol, status,
  failure_count, last_success_at, `has_password` — **password never returned**) or `{channel: null}`.
- `PUT /ftp` — create-or-update the single channel (immediate live). Password optional
  (omitted = keep existing, stored `Crypt::encryptString`).
- `POST /ftp/test` — tests a **candidate** config from the request body (5s timeout,
  write/delete probe) without saving — wrong credentials never go live unnoticed.
- `POST /ftp/pause` / `POST /ftp/resume` — channel status.
- `DELETE /ftp` — "remove": if the channel has deliveries (FK RESTRICT) →
  `status=disabled` + config wiped; else hard delete.
- Gate: `ClientUser->can_manage_delivery === true` (403 otherwise) + client must be active.

### Support changes
- Migration: `client_users.can_manage_delivery` (bool, default false) + backfill first
  active user per client; admin Clients page gets a per-user toggle; portal profile
  payload exposes it (portal hides the page).
- `AuditLogRepository::log()` gains optional `actorType/actorId` (portal context has no
  staff auth user).
- Staff notification (users with `clients,edit`) on every client FTP change.
- Client notification when the FTP channel is **paused** (manual or auto-pause after
  repeated failures) — completes the loop.

### Frontend (Next.js)
- New `/ftp` page with two tabs: **FTP channel** (form + test + pause/resume/remove)
  and **FTP notifications** (filtered from the existing notifications endpoint).
- Rail card "Delivery settings (FTP · API · alerts)" repointed from the staff admin to
  `/ftp`; user dropdown link added.

## 3. Verification plan

- [ ] `PortalFtpTest`: admin-only gate (403 for non-admin user), masked payload, PUT
      validation + immediate save, test endpoint, pause/resume, remove semantics,
      audit rows (`client_user` actor), staff notification, cross-client isolation.
- [ ] `php artisan test`; pint; schema parity.
- [ ] Live smoke: portal login as The Daily Star admin user → view/edit/test/pause;
      notifications tab.

## 4. Completion notes

_(pending)_
