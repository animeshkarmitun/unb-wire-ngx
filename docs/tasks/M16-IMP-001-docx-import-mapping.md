# Task: M16-IMP-001 — Docx import with server-side parsing + mapping preview

**Status:** ✅ Completed
**Dependencies:** None (wizard import modal exists with primitive client-side mammoth flow)
**Parent ADR:** Feature brainstorm 2026-10-18 (approved: mapping preview step, .docx only, Bijoy→Unicode deferred to Phase 3, .txt/.rtf dropped)

---

## 1. Contract (What)
- **Inputs / Validation:** Livewire temp upload `docUpload` — `required|file|mimes:docx|max:10240` (10 MB); ZipArchive integrity check in service (fake/renamed file → 422).
- **Outputs / Response:** Component state `docImport` = `{ file_name, words, paragraphs, dateline_city, blocks:[{kind, text, certain}], assignment:[field-per-block] }`; `applyDocImport()` fills wizard fields.
- **Authorization:** Page route already gates `rbac:stories,create` (routes/web.php:42-44); `importDoc()` + `applyDocImport()` re-assert `stories.create` / `stories.edit` server-side.

## 2. Logic (How)
1. `updatedDocUpload()` → validate → `DocxImportService::extract()` (ZipArchive → `word/document.xml` → paragraphs with heading/bold flags → heuristics: headline, subhead, byline, dateline city strip) → populate `docImport` + default `assignment` (headline|sub_head|byline|body|ignore).
2. Mapping preview UI in the existing `#docOverlay` modal: block rows with per-block field `<select>`, confidence dot for guessed assignments, dateline chip.
3. `applyDocImport()` → sanitize all text (`HtmlSanitizer`), wrap body blocks in `<p>`, fill headline/subHead/author/datelineCity/bodyHtml, derive brief (≤280), `quill-set-content`, toast, autosave.
4. Sample template: `DocxImportService::generateSample()` (on-the-fly minimal docx — Heading1 + bold sub-head + By line + dateline body placeholders) served by `GET /admin/news/import-sample` (`rbac:stories,create`); modal shows "Download the sample template" link + format hint; parser flags placeholder-bracket headlines (`headline_placeholder`) with an amber warning in the preview.
5. Remove orphaned client-side mammoth flow (CDN script + `showDocPreview`/`docInsert`/`pendingDoc` + `syncFromDoc`).

## 3. Context (Where)
- **Create:** `app/Services/DocxImportService.php`, `tests/Unit/Services/DocxImportServiceTest.php`, `tests/Feature/AddNewsDocxImportTest.php`
- **Modify:** `app/Livewire/Admin/AddNews.php`, `resources/views/livewire/admin/partials/add-news-modals.blade.php`, `resources/js/wizard/wizard-main.js`, `resources/views/livewire/admin/add-news.blade.php`, `docs/tasks/README.md`
- **Reference:** `App\Services\HtmlSanitizer`, `App\Livewire\Admin\AddNews::syncFromDoc` (predecessor), photo drag-drop pattern (`photo-manager.blade.php:16`)

## 4. Prompt (For the Coding AI)
> Upgrade the wizard's doc-import modal: replace client-side mammoth text dump with server-side `DocxImportService` (ZipArchive+XML, no new composer deps) via Livewire upload; render an editable mapping preview (per-block field assignment, confidence cue, dateline chip); `applyDocImport` fills the wizard and flows through normal autosave/AI gates. .docx only, 10 MB cap, everything sanitized.

## 5. Test Criteria
- [ ] Unit: heading headline detection, bold subhead, byline, EN+BN dateline strip, plain-text fallback, corrupt/empty docx exceptions
- [ ] Feature: 422 on .txt upload + on fake docx; happy path populates `docImport` blocks/mapping; `applyDocImport` fills fields + sanitized body; unauthorized role blocked
- [ ] `php -l` clean on all modified files
- [ ] `php artisan test` green

## 6. Completion Notes
- **Shipped:** `DocxImportService` (ZipArchive+XML parse, heading/bold/dateline/byline heuristics, EN+BN dateline, 300-block cap); `AddNews` doc-import state machine (`docUpload` → `docImport`+`docAssignment` → `applyDocImport`, rbac-asserted, sanitized); mapping-preview modal (per-block field select, dateline chip, guessed-headline + placeholder warnings, wire:loading state); drop→Livewire file bridge in `wizard-main.js`; sample template generated on the fly (`generateSample()`) + `GET /admin/news/import-sample` download route (static-before-dynamic ordering vs `/admin/news/{language}`) + modal link; mammoth CDN + client-side parse flow + `syncFromDoc` removed (orphaned). Plan deviation: dedicated HTTP endpoint/FormRequest replaced by Livewire `WithFileUploads` (same validation+RBAC guarantees, fewer moving parts, CSRF-free wiring) — mirrors the approved component pattern used across the wizard.
- **Tests:** `php artisan test` — full suite green (939+ passed before template round; see final run). New: 8 unit (parsing heuristics, BN dateline, corrupt/empty/oversized, template round-trip), 6 feature (non-docx 422, fake docx, real parse, template download 200/PK, template 403, RBAC denial), 3 rewritten in `AddNewsTest`.
- **Gates:** `php -l` clean; `npm run build` ✓; `php scripts/schema-parity-check.php` ✓ (no schema change).
- **Live Smoke:** Manual browser pass (2026-10-18, admin user): drag-drop .docx into the modal, mapping preview confirm, fields filled in wizard, sample template download — all working. Component render additionally covered by the import-related tests.
- **Review:** —

## 7. Prompt Ready?
- [x] Yes
