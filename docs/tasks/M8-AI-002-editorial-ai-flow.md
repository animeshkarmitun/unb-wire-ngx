# Task: M8-AI-002 — AI Editorial Assistant, Token Quota & Guardrail Flow

**Status:** ✅ Complete
**Dependencies:** COS-9 (AiProvider integration), M8-AI-001 (AI Settings)

---

## 1. The Contract (What)

### Inputs & Actions
- `AddNews::callAi(string $kind)`: Triggered with raw text (`aiRawText`), headline, or brief from the editor.
- `AddNews::applyAi(string $field)`: Applies generated content for `headline`, `brief`, `body`, `category`, or `tags`. Marks `$aiTouched[$field] = true`.
- `AddNews::publish()`: Enforces publish gate — non-breaking stories with unreviewed `ai_touched` fields are blocked from immediate publication.
- `AiService`: Audits each generation in `ai_generations` and increments `ai_token_usage_daily`.

### UI Contracts
- `add-news-ai-drawer.blade.php`: Opens `#aiDrawer` upon suggestion readiness, displays suggestion cards with explicit "Apply" buttons.
- Form inputs receiving AI suggestions render `.ai-touched` amber indicator.
- `/admin/ai-settings`: Renders live usage tokens (`#usageNow`), budget fill (`#usageFill`), and desk breakdown.

---

## 2. The Logic (How)

1. User types raw notes in `#aiRaw` and clicks `#aiGenerateBtn`.
2. Livewire dispatches `callAi('generate')`, which invokes `AiService::call()`.
3. `AiService` enforces kill switch and monthly token budget, invokes provider (`StubAiProvider` or `OpenAiProvider`), records in `ai_generations`, and upserts `ai_token_usage_daily`.
4. Livewire sets `$aiPack` and renders cards in `#aiDrawer`.
5. User clicks "Apply" on a suggestion -> field updates, `$aiTouched[$field] = true`, autosaves, and records `ai_applied` in `story_events`.
6. User attempts to publish:
   - If `ai_touched` is non-empty and `!is_breaking`, `StoryService` throws `UnprocessableEntityHttpException`, surfaced as guardrail message in UI.
   - If user edits the field or marks as breaking, publish proceeds cleanly.

---

## 3. The Context (Where)

- Target files:
  - `resources/views/livewire/admin/partials/add-news-ai-drawer.blade.php`
  - `resources/js/wizard/wizard-main.js`
  - `app/Livewire/Admin/AddNews.php`
  - `tests/e2e/helpers/seed-data.php`
  - `tests/e2e/ai-editorial-quota.spec.ts`

---

## 4. Verification & Live Smoke

- `npx playwright test tests/e2e/ai-editorial-quota.spec.ts`: 5 passed (52.7s)
- `php scripts/schema-parity-check.php`: All schema parity checks PASSED
- `php artisan test`: 587 passed, 1 skipped (2143 assertions)
- Combined E2E suite (`tests/e2e/ai-editorial-quota.spec.ts`, `notification-center.spec.ts`, `audit-browser.spec.ts`, `story-history-diff-restore.spec.ts`, `superadmin-rbac.spec.ts`, `distribution-log.spec.ts`, `api-media-billing.spec.ts`): 31 passed (1.7m)
- Live routes smoked: `/admin/add-news`, `/admin/ai-settings`
