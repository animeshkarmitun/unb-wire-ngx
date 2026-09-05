# Task: M8-CLIENT-001 — Clients Manager faithful

**Status:** ✅ Completed
**Dependencies:** M8-UI-002, M2-DB-002
**Parent ADR:** app-data/clients.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/clients` `auth,verified,rbac:clients,view`; query: search, status (`all`, `active`, `paused`, `deactivated`), tier (`all`, `Enterprise`, `National`, `Digital`, `Trial`), sort (`name`, `status`, `expires_at`, `created_at`, `usage`).
- **Outputs / Response:**
  - Breadcrumb: `Home / Clients & Subscribers`.
  - Topbar with `+ Onboard new client` button (opens 3-step wizard) and `Export CSV` button (RFC 4180 streaming download).
  - 5-stat metric strip matching prototype: Total clients (8), Active (5), Paused (2), Renewals in 45d (3), Delivery issues (1).
  - Filter bar: search input (`Search clients by name, code...`), 4 status chips (`All`, `Active`, `Paused`, `Deactivated`), Tier select, Sort select.
  - Sticky navy bulk action bar appearing when rows are checked: count badge (`N clients selected`), Pause selected, Change package, Export selected, Clear.
  - Client table list: client name, code, delivery issue badge if any, 4 delivery channel indicators (Email, FTP, API, Push/Webhook) with tooltips, package name, tier pill (`.badge-enterprise`, `.badge-national`, `.badge-digital`, `.badge-trial`), usage progress bar with percentage, status pill (`.active`, `.paused`, `.deactivated`), and detail chevron.
  - Detail Drawer (500px):
    - Tab 1 Overview: client info, contact details, edit form, delivery health status, newsroom internal notes textarea with save button.
    - Tab 2 Channels: Email, FTP, API Key, Webhook configuration cards; interactive modal for FTP editing; test connection buttons; API key copy and key regeneration modal.
    - Tab 3 Package: current package details, effective dates, switch package select, add-on toggles (Photos, Infographics, Breaking SMS, Video B-roll), and save package configuration.
    - Tab 4 Activity: chronological audit timeline with timestamp and user attributions.
  - Modals:
    - Pause Client modal with reason dropdown and pause duration options.
    - Deactivate Client modal with confirmation and reason text.
    - 3-step Onboard Wizard (`Step 1: Details` -> `Step 2: Package & channels` -> `Step 3: Review & activate`).
- **Authorization:** `clients` module per `role_permissions` matrix.

---

## 2. Logic (How)
1. Created `resources/css/clients-manager.css` importing 1:1 CSS from `app-data/clients.html` and registered into `resources/css/app.css`.
2. Updated `database/seeders/ClientSeeder.php` to seed the 8 canonical prototype clients (The Daily Star, Prothom Alo, Jamuna Television, Dhaka Tribune, Samakal, Bangladesh Pratidin, BDNews24, UNB Internal Desk) with full channels (email, ftp, api, webhook), packages, contacts, and delivery health.
3. Refactored `app/Livewire/Admin/ClientsManager.php` with 5-stat computation, multi-select bulk operations, detail drawer with 4 functional tabs (Overview notes, Channels interactive CRUD / test / API key regeneration, Package switcher with add-ons, Activity log), Pause modal, Deactivate modal, 3-step Onboard Wizard, and RFC 4180 CSV export.
4. Implemented `resources/views/livewire/admin/clients-manager.blade.php` matching 1:1 prototype DOM and styling tokens.
5. Created PHPUnit feature test suite `tests/Feature/ClientsManagerTest.php` (14 tests, 89 assertions).
6. Created Playwright E2E test suite `tests/e2e/clients-faithful.spec.ts` (5 tests).

---

## 3. Context (Where)
- **Files Created / Modified:**
  - `resources/css/clients-manager.css`
  - `resources/css/app.css`
  - `composer.json`
  - `app/Livewire/Admin/ClientsManager.php`
  - `resources/views/livewire/admin/clients-manager.blade.php`
  - `database/seeders/ClientSeeder.php`
  - `tests/Feature/ClientsManagerTest.php`
  - `tests/e2e/clients-faithful.spec.ts`
  - `docs/tasks/M8-CLIENT-001-clients-manager.md`
  - `docs/tasks/README.md`

---

## 4. Test Criteria
- [x] Page header, breadcrumb, topbar actions, 5-stat strip, search bar, 4 status chips, tier filter, sort filter, and client list render
- [x] Search input filters clients by name and code in real time
- [x] Status chips filter clients (`All`, `Active`, `Paused`, `Deactivated`)
- [x] Tier dropdown and sort options reorder/filter the client list
- [x] Multi-selection reveals sticky navy bulk action bar
- [x] Bulk pause and bulk package update actions function correctly
- [x] Client row click opens 500px detail drawer with 4 tabs
- [x] Overview tab displays contact information and updates internal notes
- [x] Channels tab displays 4 channels with working test connection, FTP update modal, and API key regeneration
- [x] Package tab supports switching package, toggling add-ons, and updating effective dates
- [x] Pause and Deactivate modals process status changes and update client lists
- [x] 3-step Onboard Wizard navigates steps and creates active clients
- [x] CSV export generates RFC 4180 download for all or selected clients
- [x] Automated PHPUnit tests pass (`ClientsManagerTest`: 14/14 passed, 89 assertions)
- [x] Automated Playwright E2E tests pass (`clients-faithful.spec.ts`: 5/5 passed)

---

## 5. Completion Notes
- **Shipped:** 1:1 faithful Clients Manager with 5-stat metric strip, status chips, tier/sort filters, sticky navy bulk bar, 4-channel indicator icons, 500px 4-tab detail drawer (Overview, Channels, Package, Activity), Pause modal, Deactivate modal, 3-step Onboard Wizard, and RFC 4180 CSV export.
- **Tests:** `php artisan test --filter=ClientsManagerTest` (14 passed, 89 assertions); full suite: 156 passed (508 assertions).
- **E2E:** `npx playwright test tests/e2e/clients-faithful.spec.ts` (5 passed).
- **Parity Gate:** `php scripts/schema-parity-check.php` all checks passed.
- **Live Smoke:** Clear compiled caches (`config:clear`, `route:clear`, `view:clear`), `/admin/clients` renders 200 with all components.
