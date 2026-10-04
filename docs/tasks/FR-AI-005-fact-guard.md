# Task: FR-AI-005 — New-facts warning (FactGuard)

**Status:** ✅ Completed
**Dependencies:** M13-AI-003 (WireStyleLinter)
**Parent ADR:** FR-AI-005 (`docs/fr-cross-check-report.md` P2 — was "missing"; complements the COS-21 quality gate)

---

## 1. Contract (What)
"If the AI output introduces facts not present in the source (numbers, names, quotes), the drawer flags them prominently ('N new facts — verify before use')." Implemented as deterministic extraction (`App\Services\Ai\FactGuard`, no AI round-trip — works with the stub provider):
- **Numbers** (incl. currency/percent/unit suffixes) in output but not in source
- **Quoted spans** (8+ chars) not present in source — invented quotes are the most dangerous hallucination
- **Proper-noun sequences** (2+ capitalized words) not in source — heuristic

`AiService::call` attaches `new_facts` to the pack **and** persists it on the `ai_generations` row (was hardcoded `null`). The AI drawer renders a prominent "N new facts — verify before use" card; the publish-gate checklist modal's `#gateFacts` now shows the real count (was hardcoded "0 facts flagged").

---

## 2. Context (Where)
- **Files Modified:** `app/Services/Ai/FactGuard.php` (new), `app/Services/AiService.php` (extract before `AiGeneration::create`, attach to pack, persist), `resources/views/livewire/admin/partials/add-news-ai-drawer.blade.php` (warning card), `add-news-modals.blade.php` (live count), `tests/Feature/Services/FactGuardTest.php` (new, 8 tests)

---

## 3. Test Criteria
- [x] Numbers/quotes/names absent from source → flagged; present → clean
- [x] Empty output → no facts
- [x] `AiService` pack carries `new_facts` (array)
- [x] Full `php artisan test` green

---

## 4. Completion Notes
- **Shipped:** Per §1. Extraction runs before the `AiGeneration` insert so the audit row records the real fact list.
- **Tests:** `FactGuardTest` 8 passed — full suite green; `php -l` clean; parity PASSED.
- **Review:** PR.
