# Task: M8-DELIV-001 — Delivery Settings Manager faithful

**Status:** ✅ Complete
**Dependencies:** M8-UI-002, M2-DB-007, M8-CLIENT-001
**Parent ADR:** app-data/delivery-settings.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/delivery-settings` `auth,verified,rbac:distribution,view`.
- **Outputs / Response:**
  - Breadcrumb: `Home / Distribution / Delivery settings`.
  - Masthead & Client Context:
    - Heading: `Delivery settings`.
    - Subtitle: `Zero-touch delivery — UNB pushes the wire and licensed media straight into your systems, the moment it publishes. Configure once; no manual downloading needed.`
    - Client switcher dropdown allowing admin to toggle between clients (defaults to The Daily Star).
  - Card 1: Auto-push (FTP / SFTP) (`.card`):
    - Navy gradient icon (`.card-ic.navy`).
    - Master switch (`#pushMaster`).
    - Status row with pulse indicator (`.dot.live`), connection endpoint, last successful push timestamp, and failure stats.
    - `Test connection` button (`#testBtn`) with simulation / validation action.
    - `Edit credentials` button (`#credBtn`) toggling `#credFields`: SFTP host, port, username, authentication type (SSH key / Password), credentials password/key.
    - Channel rows: English news wire (`NewsML XML`), UNB photos (`JPEG + IPTC`), UNB video (`MP4 · 1080p`), Tigers Test series pack (`JPEG + MP4`), AP World photo pack (with lock indicator if not subscribed).
    - Wire format dropdown: NewsML-G2 (XML), NITF (XML), JSON (UNB v1), RSS 2.0.
    - Push schedule dropdown: Instantly on publish, Batch — every 15 minutes, Batch — hourly.
    - `Save push settings` action (`#pushSave`).
  - Card 2: API access (`.card`):
    - Crimson gradient icon (`.card-ic.crimson`).
    - Base endpoint with copy action.
    - Scoped API key with masked default view, Reveal/Hide toggle, Copy action, and 2-step confirmation Regenerate action.
    - Webhook URL input.
    - Webhook notification event triggers (Breaking news, New media pack, Exclusive content, Embargoed asset).
    - `Save API settings` action (`#apiSave`).
  - Card 3: Email alerts (`.card`):
    - Amber gradient icon (`.card-ic.amber`).
    - Dynamic email recipient chip collection with ✕ remove buttons.
    - Email addition field with email format validation.
    - Alert category switches (Breaking news, New media pack, Exclusive content, Saved searches).
    - `Save alert settings` action (`#alertSave`).
  - Card 4: Download & license history (`.card`):
    - Green gradient icon (`.card-ic.green`).
    - `Export CSV` action (`#histCsv`).
    - Audit table showing recent downloads with asset name, license badge (`UNB`, `AP`, `AP add-on`, `UNB · exclusive`), downloaded by, and timestamp.
    - AP license compliance audit notice (`.audit-note`).
  - Card 5: Engine Dispatch Rules (Global Delivery Config):
    - Retry attempts, Backoff seconds, Auto-pause after threshold, At-least-once delivery toggle.
- **Authorization:** `distribution` module per `role_permissions` matrix (`view`, `edit`).

---

## 2. Logic (How)
1. Extract and create `resources/css/delivery-settings.css` matching all styles from `app-data/delivery-settings.html` and import into `resources/css/app.css`.
2. Seed sample download logs in `database/seeders/MediaSeeder.php` for `The Daily Star` so license history renders real data.
3. Refactor `app/Livewire/Admin/DeliverySettings.php`:
   - Load client context (defaulting to The Daily Star / active client).
   - Manage FTP / SFTP push config, connection testing, credentials drawer, channel toggles, wire format, and push schedule.
   - Manage API access (endpoint, masked/revealed key, key rotation via `ApiKeyService::rotate()`, webhook URL, notification triggers).
   - Manage Email alerts (recipient chips add/remove with email validation, alert type toggles).
   - Manage Download & license history query and CSV export generation.
   - Manage engine dispatch rules (`retryAttempts`, `backoffSeconds`, `autoPauseAfter`, `atLeastOnce`).
4. Rewrite `resources/views/livewire/admin/delivery-settings.blade.php` matching prototype markup 1:1.
5. Create feature test `tests/Feature/DeliverySettingsTest.php` and Playwright E2E test `tests/e2e/delivery-settings-faithful.spec.ts`.
6. Run quality gates (`pint`, `npm run build`, `php artisan test`, `npx playwright test`, `php scripts/schema-parity-check.php`, `php artisan optimize:clear`).

---

## 3. Verification & Completion Notes
- `php artisan test --filter=DeliverySettingsTest`: 11 passed (48 assertions).
- `npx playwright test tests/e2e/delivery-settings-faithful.spec.ts`: 5 passed (49.2s).
- `php artisan test`: 204 passed (762 assertions), 0 regressions.
- `php scripts/schema-parity-check.php`: All 12 parity checks passed.
- `npm run build`: Vite v7.3.6 production build passed in 3.11s.
- `vendor/bin/pint --test`: Passed with 0 violations.
- Live smoke test performed on `/admin/delivery-settings` with admin session per `docs/workflow/live-test-runbook.md`.

