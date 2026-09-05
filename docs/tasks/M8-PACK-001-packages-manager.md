# Task: M8-PACK-001 — Packages & Add-ons Manager faithful

**Status:** ✅ Completed
**Dependencies:** M8-UI-002, M2-DB-007, M8-CLIENT-001
**Parent ADR:** app-data/packages.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/packages` `auth,verified,rbac:packages,view`.
- **Outputs / Response:**
  - Breadcrumb: `Home / Packages & add-ons`.
  - Topbar with action buttons: `New add-on` (opens add-on editor modal) and `New package` (opens package editor modal).
  - 4-Stat Metric Strip: Live packages (`3`), Add-ons live (`2`), Clients covered (`7`), Monthly recurring revenue (`৳4.4 lakh` or formatted).
  - Section 1: Subscription packages (`.pkg-grid`):
    - Cards with gradient top (`g1`–`g8`), status badge (`live`, `draft`, `archived`), name, monthly price, and description.
    - Feature list with icons: Wire access, Photo quota, Video clips, Exclusive stories, API access, Priority desk support.
    - Client avatar cluster (`pk-avatars`) displaying initials of up to 4 assigned clients, plus count.
    - Action buttons: Edit (opens modal), Duplicate (clones as draft), Archive (opens modal with client reassignment warning), Restore (if archived), Delete (guarded against packages with clients).
    - Create new package card (`.pkg-new`).
  - Section 2: Add-ons (`.addon-list`):
    - Table header: Add-on, Description, Available with, Price, Status, Actions.
    - Add-on rows: color dot (`g1`–`g8`), name, client count badge, description, available tiers (`All packages` or combined), price, clickable live/draft status badge, edit and delete actions.
  - Modals:
    - 2-Column Package Editor Modal (`modal-xl`): inputs for name, price, card color (8 dots `g1`–`g8`), description, wire access select, photo quota select, feature checkboxes, and synchronized Client View Preview with live gradient, name, price, and feature checkmarks.
    - Add-on Editor Modal: name, price, description, available with tier checkboxes.
    - Archive / Reassign Modal: warning box listing attached clients with reassignment package select dropdown.
- **Authorization:** `packages` module per `role_permissions` matrix.

---

## 2. Logic (How)
1. Created `resources/css/packages-manager.css` containing 1:1 CSS from `app-data/packages.html` and imported into `resources/css/app.css`.
2. Updated `database/seeders/PackageSeeder.php` to seed the 4 canonical subscription packages (*Premium Wire + Media*, *Standard Wire*, *Basic Headlines*, *District Wire*) and 3 add-on packages (*AP World pack*, *Bangla service*, *Sports data feed*).
3. Updated `database/seeders/ClientSeeder.php` to seed `client_packages` subscriptions for add-on services in addition to core wire packages.
4. Refactored `app/Livewire/Admin/PackagesManager.php` with complete state and methods:
   - Dynamic 4-stat calculation (live packages, live add-ons, covered clients, MRR).
   - Package editor modal with synchronized live client preview, draft and live save workflows, and 28-character slug capping.
   - Package duplication as draft with `(copy)` suffix.
   - Archive modal with active client reassignment dropdown and cascading subscription migration.
   - Restore and delete operations with safeguards.
   - Add-on modal for creation and editing with tier selection.
   - Clickable Add-on status chip toggling between live and draft.
   - Delete Add-on guarded against attached clients.
   - RBAC authorization on all actions (`packages:view`, `create`, `edit`, `delete`).
5. Implemented `resources/views/livewire/admin/packages-manager.blade.php` matching prototype markup 1:1.
6. Created PHPUnit feature test suite `tests/Feature/PackagesManagerTest.php` (10 tests, 67 assertions).
7. Created Playwright E2E test suite `tests/e2e/packages-faithful.spec.ts` (5 tests).

---

## 3. Context (Where)
- **Files Created / Modified:**
  - `resources/css/packages-manager.css` [NEW]
  - `resources/css/app.css` [MODIFIED]
  - `database/seeders/PackageSeeder.php` [MODIFIED]
  - `database/seeders/ClientSeeder.php` [MODIFIED]
  - `app/Livewire/Admin/PackagesManager.php` [MODIFIED]
  - `resources/views/livewire/admin/packages-manager.blade.php` [MODIFIED]
  - `tests/Feature/PackagesManagerTest.php` [NEW]
  - `tests/e2e/packages-faithful.spec.ts` [NEW]
  - `docs/tasks/M8-PACK-001-packages-manager.md` [NEW]
  - `docs/tasks/README.md` [MODIFIED]

---

## 4. Test Criteria
- [x] Page header, breadcrumb, topbar action buttons, 4-stat strip, package grid, and add-on table render
- [x] 4 stats computed accurately (Live packages: 3, Live add-ons: 2, Clients covered: 7, MRR)
- [x] Package Editor Modal opens, color dots change gradient, inputs update live card preview, and saves as draft or live
- [x] Duplicate package creates an archived draft copy with `(copy)` suffix
- [x] Archive modal identifies attached clients and reassigns them to selected active package upon confirmation
- [x] Restore package transitions archived package back to active
- [x] Delete package is blocked when clients are attached and succeeds when 0 clients exist
- [x] Add-on Editor Modal creates new add-on with selected package tiers
- [x] Add-on status badge interactively toggles between live and draft
- [x] Delete add-on is blocked when clients use it
- [x] RBAC blocks unauthorized users from viewing or mutating packages
- [x] PHPUnit feature tests pass (`PackagesManagerTest`: 10/10 passed, 67 assertions)
- [x] Playwright E2E tests pass (`packages-faithful.spec.ts`: 5/5 passed)

---

## 5. Completion Notes
- **Shipped:** 1:1 faithful Packages & Add-ons Manager with 4-stat strip, subscription package cards with client avatar clusters and feature checks, add-on table with live/draft toggle, 2-column package editor modal with live client preview, add-on editor modal, and archive modal with client reassignment.
- **Tests:** `php artisan test --filter=PackagesManagerTest` (10 passed, 67 assertions); full test suite: 166 passed (575 assertions).
- **E2E:** `npx playwright test tests/e2e/packages-faithful.spec.ts` (5 passed).
- **Parity Gate:** `php scripts/schema-parity-check.php` all checks passed.
- **Live Smoke:** Caches cleared (`optimize:clear`), assets built (`npm run build`), routes verified 200.
