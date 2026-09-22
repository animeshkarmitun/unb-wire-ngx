# Task: M13-AI-002 — AI auto-publish happy path: allowlisted category test + gate fix

**Status:** ✅ Completed
**Dependencies:** M13-RBAC-001 (test harness roles)
**Parent ADR:** DEC-007 (AI guardrails), FR-AI-006/008 (publish gate + auto-publish allowlist)

---

## 1. Contract (What)
- **Inputs / Validation:** `AddNews::publish()` on an AI-touched (`ai_touched` non-empty), non-breaking story; settings row `ai.desk` with `autoPublish=true` and `autoCats=['<category name_en>']`.
- **Outputs / Response:** Story transitions `draft → in_review → approved → published`; `successState='published'`; `story_events` chain written; `FanoutStory` + `ProcessIndexOutbox` dispatched.
- **Authorization:** `rbac stories,publish` (Editor/Admin). Uploader still 403.
- **Spec:** FR-AI-006 — the publish gate is skipped **only** when auto-publish is on AND the category is allowlisted; FR-AI-008 — allowlist holds routine category **names** (weather, sports results, …), matching the AiSettings UI which stores `name_en` strings.

---

## 2. Logic (How)
1. `StoryService::transition(…, 'published')` re-checks the AI gate (app/Services/StoryService.php:137-144).
2. **Bug:** gate compares `$story->category_id` (bigint) against `autoCats` (category **name** strings saved by `AiSettings::toggleCategory`) → `in_array(..., true)` never matches → allowlisted categories were still blocked even with `autoPublish=true`. Existing test only covered the blocked path (`autoPublish=false`), so the mismatch was never caught (COS-9/M13-AI-001 audit flagged the missing happy path).
3. **Fix:** resolve the story's category `name_en` (via `loadMissing('category')`, no lazy-load violation) and compare that against `autoCats`, strict `in_array`.

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Services/StoryService.php` (gate comparison, line ~140)
  - `tests/Feature/AddNewsTest.php` (new happy-path test)
- **Reference Files:**
  - `app/Livewire/Admin/AiSettings.php` (autoCats = names)
  - `app-data/v1-functional-requirements.md` FR-AI-006/008
  - `docs/knowledge-inventory/domain.md` (AI guardrails)

---

## 4. Prompt (For the Coding AI)
> In `tests/Feature/AddNewsTest.php`, add `test_ai_touched_allowlisted_category_publishes_when_auto_publish_on`: set `settings.ai.desk` to `autoPublish=true, autoCats=['Business']`; create a Business-category story; `callAi('preedit')`, `applyAi('headline')`, `publish()`; assert DB `status='published'` + `successState='published'`. It will fail (gate bug). Then fix `StoryService::transition` to compare the story category's `name_en` (via `loadMissing('category')`) against `autoCats` instead of `category_id`. Re-run until green; run full suite.

---

## 5. Test Criteria
- [x] Happy path: autoPublish=true + Business in autoCats + AI-touched → published (new test)
- [x] Blocked path still blocks: autoPublish=false → not published (existing test, must stay green)
- [x] Non-allowlisted category with autoPublish=true still blocks (companion test)
- [x] `php -l` clean on all modified PHP files
- [x] Full `php artisan test` green

---

## 6. Completion Notes
- **Shipped:** TDD red→green. New happy-path test exposed gate bug: `StoryService::transition` compared `$story->category_id` (bigint) against `autoCats` (category **names** persisted by `AiSettings::toggleCategory`) — strict `in_array` could never match, so allowlisted categories stayed blocked even with `autoPublish=true` (FR-AI-006/008 violated). Fix: compare `$story->loadMissing('category')->category?->name_en` against `autoCats`. Null-safe: story without category stays blocked. Companion negative test guards the non-allowlisted path (autoPublish on, category outside allowlist → still blocked).
- **Tests:** `php artisan test` — 719 passed, 1 skipped (pg-specific), 0 failures; `php -l` clean on both modified files; `php scripts/schema-parity-check.php` — all PASSED.
- **Live Smoke:** `php artisan optimize:clear` (all 6 caches); `artisan serve` on :8010 — `/api/v1/portal/feed` 200, `/admin/news/en` guest 302→login (auth enforced), `/login` 200; `storage/logs/laravel.log` zero ViewException/MissingAttributeException from smoke (pre-existing historical audit-log-browser trace untouched, unrelated to this diff). Server stopped after smoke.
- **Review:** (PR review — pending CI)

---

## 7. Prompt Ready?
- [x] Yes
