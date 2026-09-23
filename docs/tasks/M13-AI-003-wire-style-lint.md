# Task: M13-AI-003 — AI pre-edit quality gate (UNB wire standards lint)

**Status:** ✅ Completed
**Dependencies:** M5-AI-001
**Parent ADR:** COS-21 spec (options 1 + 2 recommended: post-generation lint + few-shot prompts); FR-AI-002 style rules

---

## 1. Contract (What)
Lint AI-generated copy against UNB wire standards before the editor sees/applies it (issue option 1), and steer generation with few-shot examples (issue option 2):
- **Rules linted:** wire dateline opener (`CITY, Mon DD —`) → error if missing; `END/UNB` sign-off → error if missing; `[VERIFY]` flags surfaced as warnings with count (uncertain facts must never pass silently); past-tense lead heuristic (present-tense reporting verbs) → warning; unattributed-quote heuristic → warning.
- **Bangla copy:** only the language-neutral `[VERIFY]` rule applies (EN structure rules would false-positive).
- **Output:** `style_lint` array (`rule`/`severity`/`message`) attached to the AI pack → rendered as a "Style lint" card in the AI drawer (non-blocking; apply stays the editor's call — option 4 flag surface).

---

## 2. Logic (How)
1. `App\Services\Ai\WireStyleLinter::lint($headline, $bodyHtml, $bangla)` — deterministic checks per §1 (body HTML stripped before analysis).
2. `AiService::call` attaches `style_lint` to the returned pack (all copy kinds; `tags` packs lint nothing since body is empty).
3. `add-news-ai-drawer.blade.php` renders the lint card when violations exist.
4. Few-shot: one UNB-style example body appended to the system style prompt in **all three prompt surfaces** (`OpenAiProvider` inline style, `AiSettings::DEFAULT_STYLE_PROMPT`, `SettingSeeder` `stylePrompt`).

---

## 3. Context (Where)
- **Files Created/Modified:** `app/Services/Ai/WireStyleLinter.php` (new), `app/Services/AiService.php`, `resources/views/livewire/admin/partials/add-news-ai-drawer.blade.php`, `app/Services/Ai/OpenAiProvider.php`, `app/Livewire/Admin/AiSettings.php`, `database/seeders/SettingSeeder.php`, `tests/Feature/Services/WireStyleLinterTest.php` (new, 8 tests)

---

## 4. Test Criteria
- [x] Clean wire copy → zero violations
- [x] Missing dateline + sign-off → errors
- [x] `[VERIFY]` counted as warnings; tense + unattributed-quote heuristics fire; attributed quote clean
- [x] Bangla limited to `[VERIFY]`; empty body clean
- [x] Full `php artisan test` green

---

## 5. Completion Notes
- **Shipped:** Per §1/§2. Lint is deterministic (no AI round-trip) so it works with the stub provider — stub output correctly flags dateline/signoff gaps. Options 3 (self-check prompt) and a blocking apply-gate deliberately not built (issue recommends 1+2; drawer card covers the flag surface).
- **Tests:** `WireStyleLinterTest` 8 passed — full suite **760 passed, 1 skipped, 0 failures**; `php -l` clean; parity PASSED.
- **Incident (recorded):** the first prompt-string edit went through PowerShell console and corrupted UTF-8 em-dashes in 3 PHP strings (broke `AiSettings` Livewire state serialization — 7 tests red). Reverted and re-applied via UTF-8-safe editing; clean re-run. Lesson: never pipe non-ASCII literals through PS console into source files.
- **Live Smoke:** `optimize:clear` implied by suite boot; prompt strings verified via `php -l` + suite green (AiSettingsTest round-trips `stylePrompt` through settings save/load).
- **Review:** PR.
