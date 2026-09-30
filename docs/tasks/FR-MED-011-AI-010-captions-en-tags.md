# Task: FR-MED-011 + FR-AI-010 — AI photo captions/tags + Bangla English search tags

**Status:** ✅ Completed
**Dependencies:** M13-AI-003 (quality gate), FR-AI-005 (FactGuard)
**Parent ADR:** FR-MED-011 + FR-AI-010 (`docs/fr-cross-check-report.md` P2 "missing" pair); DEC-016

---

## 1. Contract (What)
**FR-MED-011 — AI captions & tags for photos:** when the Photos-desk toggle (`preeditPhotos`, FR-AI-007 — previously had **no consumer**) is on, the photo inspector's **"Suggest with AI"** offers a caption + English tags; the uploader **confirms via "Use" buttons** (fills the existing inspector fields — they never type) and saves with the existing *Save changes*. AiResult gained an optional `caption`; `StubAiProvider` 'tags' returns one.

**FR-AI-010 — AI English search tags for Bangla stories:** background `GenerateEnTags` job dispatched when a **bn** draft is created (AI kind `en_tags` — the CHECK-supported kind nothing emitted before). Suggestions live in `ai_generations`; the wizard's step-3 tags area shows *"Suggested search tags (English, search-only)"* chips with **Confirm search tags** → persists `stories.en_search_tags` (**DEC-016** jsonb column). Confirmed tags feed the Meilisearch document as a `search_tags` field (`ProcessIndexOutbox::buildPayload`) — **search indexes only, never displayed** (wire formats + portal still render display `tags`).

---

## 2. Context (Where)
- **Files:** `database/migrations/2026_09_24_000100_add_en_search_tags_to_stories.php` (DEC-016), `app/Models/Story.php`, `app/Jobs/GenerateEnTags.php` (new), `app/Services/StoryService.php` (bn dispatch), `app/Services/Ai/AiResult.php` + `StubAiProvider.php` (caption + en_tags kinds), `app/Jobs/ProcessIndexOutbox.php` (search_tags), `app/Livewire/Admin/AddNews.php` (suggestions + `confirmEnTags`), `app/Livewire/Admin/PhotoManager.php` (`suggestAiMetadata`/`applyAiSuggestion`), step3 + inspector blades, `tests/Feature/AiCaptionsAndEnTagsTest.php` (new, 6 tests), DEC-016 + data-model sync

---

## 3. Test Criteria
- [x] Photo suggestions respect `preeditPhotos` toggle; suggest + Use-apply fills inspector fields (confirm-before-save)
- [x] bn draft dispatches `GenerateEnTags`; job records `en_tags` suggestions
- [x] `confirmEnTags` persists `en_search_tags`; outbox payload carries `search_tags`
- [x] Full suite green · parity PASSED · `migrate:fresh --seed` clean

---

## 4. Completion Notes
- **Shipped:** Per §1/§2. `OpenAiProvider` keeps its existing kinds (stub is the tested path); extending real-provider prompts for `en_tags`/caption noted for the OpenAI-hardening task.
- **Tests:** `AiCaptionsAndEnTagsTest` 6 passed — full suite **798 passed, 1 skipped, 0 failures**; parity PASSED (DEC-016 patch); `migrate:fresh --seed` clean.
- **Review:** PR.
