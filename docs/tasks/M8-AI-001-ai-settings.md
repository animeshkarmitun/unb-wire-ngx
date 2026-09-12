# Task: M8-AI-001 — AI Settings Manager faithful

**Status:** ✅ Completed
**Dependencies:** M8-UI-002, M2-DB-001, M8-ROLE-001
**Parent ADR:** app-data/ai-settings.html

---

## 1. Contract (What)
- **Inputs / Validation:** `GET /admin/ai-settings` `auth,verified,rbac:settings,view`.
- **Outputs / Response:**
  - Breadcrumb: `Home / Settings / AI settings`.
  - Master Status Banner (`.status-banner`):
    - Pulsing dot indicator (`.pulse`).
    - Title: `AI pre-edit is active` vs `All AI features are disabled (kill switch)`.
    - Subtitle: `Suggestions in the editor, photo captions and Bangla→English assist` vs `Manual workflow unaffected — re-enable below`.
    - Auto-publish status: `Auto-publish: OFF` (green) / `Auto-publish: ON` (crimson-dark).
    - Status style toggled to `.off` when killed.
  - Card 1: ✦ AI pre-edit (`.card`):
    - Subtitle explaining LLM suggestions in editor.
    - 3 switches: English News (`preeditEn`), Bangla News (`preeditBn`), UNB Photos (`preeditPhotos`).
  - Card 2: ⚡ AI auto-publish (`.card`):
    - Subtitle with default OFF warning.
    - Dynamic notice box: `.danger-box` when OFF, `.warn-box` when ON.
    - Danger switch: `Enable AI auto-publish` (`.sw.danger`).
    - Modal confirmation requirement before enabling.
    - Allowlisted categories chip row:
      - Toggles for `Weather`, `Sports results`, `Market close`, `Currency rates`, `Bangladesh`, `World`, `Business`.
      - Disabled/dimmed when auto-publish is OFF.
    - Safety hint.
  - Card 3: Token usage & budget (`.card`):
    - Monthly progress bar (`.usage-bar`).
    - Usage summary: e.g. `312,400 of 500,000 tokens this month · est. cost ৳4,180`.
    - Breakdown data table per desk: English News, Bangla News, UNB Photos (calls, tokens).
    - Monthly token cap input with min 50,000 step 50,000.
    - Pre-edit cost hint.
  - Card 4: House style prompt (`.card`):
    - Subtitle explaining system prompt.
    - Resizable textarea with canonical UNB wire copy editor house style.
    - Audit log hint.
  - Card 5: Emergency kill switch (`.kill-zone`):
    - Crimson border and warning background.
    - Subtitle explaining instant newsroom-wide disable.
    - Instant action button: `Disable all AI features now` vs `Re-enable AI features` (`.kill-btn.restore`).
  - Bottom Sticky Save Bar (`.save-bar`):
    - `Reset to defaults` button.
    - `Save settings` button.
  - Confirmation Modal:
    - `⚠ Enable AI auto-publish?` warning modal with `Keep it off` and `Yes, enable for allowlist only` actions.
- **Authorization:** `settings` module per `role_permissions` matrix (`view`, `edit`).

---

## 2. Logic (How)
1. Extract and create `resources/css/ai-settings.css` matching all styles from `app-data/ai-settings.html` and import into `resources/css/app.css`.
2. Create `database/seeders/SettingSeeder.php` to seed the canonical default `ai.desk` settings and monthly token usage rollups in `ai_token_usage_daily`.
3. Register `SettingSeeder::class` in `database/seeders/DatabaseSeeder.php`.
4. Refactor `app/Livewire/Admin/AiSettings.php`:
   - Initialize state with defaults or DB settings (`preeditEn`, `preeditBn`, `preeditPhotos`, `autoPublish`, `autoCats`, `monthlyCap`, `stylePrompt`, `killed`, `model`).
   - Dynamic monthly usage and cost calculations.
   - Per-desk calls & token breakdown from `ai_token_usage_daily`.
   - Auto-publish confirmation modal lifecycle (`toggleAutoPublish`, `confirmAutoPublish`, `cancelAutoPublish`).
   - Category chip toggling (`toggleCategory`).
   - Emergency kill switch toggle (`toggleKill`) with audit logging (`ai.kill_switch.on` / `ai.kill_switch.off`).
   - Reset to defaults (`resetDefaults`).
   - Save settings (`save`) with validation and audit logging (`ai.settings.updated`).
5. Rewrite `resources/views/livewire/admin/ai-settings.blade.php` matching the exact prototype markup 1:1.
6. Write feature tests in `tests/Feature/AiSettingsTest.php` covering permissions, toggles, modal confirmation, category allowlisting, token caps, kill switch, resets, and audit logs.
7. Write Playwright E2E tests in `tests/e2e/ai-settings-faithful.spec.ts` asserting DOM fidelity, interaction flows, kill switch visual states, modal flows, and toast alerts.
8. Run quality gates (`pint`, `npm run build`, `php artisan test`, `npx playwright test`, `php scripts/schema-parity-check.php`, `php artisan optimize:clear`).

---

## 3. Verification
- `php artisan test --filter=AiSettingsTest`: All 12 feature tests passed (67 assertions).
- `php artisan test`: All 193 tests passed (714 assertions) with 0 regressions.
- `npx playwright test tests/e2e/ai-settings-faithful.spec.ts`: All 4 Playwright E2E tests passed (43.0s).
- `php scripts/schema-parity-check.php`: All schema parity checks PASSED.
- `vendor/bin/pint --test`: Clean PSR-12 code style.
- `npm run build`: Assets compiled cleanly in 3.18s.
- Live smoke pass: `php artisan optimize:clear` executed cleanly.
