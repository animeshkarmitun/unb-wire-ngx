# Task: M16-IMP-002 — Inline story images (library-backed embeds)

**Status:** ✅ Completed
**Dependencies:** M16-IMP-001 (same wizard surface, adds to the import milestone)
**Parent ADR:** Inline-images brainstorm 2026-10-18 (approved: library-backed embeds only, no base64/foreign URLs, reconcile at autosave, gallery attachments untouched)

---

## 1. Contract (What)
- **Inputs / Validation:** Archive-modal "inline" mode insert (`attachInline(assetId,…)` — idempotent, no toggle-off); body images must carry `data-media-id` + scheme-safe `src`.
- **Outputs / Response:** `body_html` may contain `<img src data-media-id alt>`; foreign/unattached images stripped at two layers (sanitizer + autosave reconcile).
- **Authorization:** No new surface — wizard actions under existing `rbac:stories,create` page gate + `stories.edit` asserts.

## 2. Logic (How)
1. `HtmlSanitizer`: allow `img` tag with `src/alt/data-media-id` only; **img without `data-media-id` removed entirely** (kills base64 pastes/hotlinks at layer 1).
2. Editor: 🖼 toolbar button opens the existing photo archive modal in "inline" mode (single-select, cursor index captured before modal opens); insert = `attachInline()` (registers story_media, role `inline`) + `quill.insertEmbed` + manual `syncBody` (embed fires source `api`).
3. `autosave()` reconcile (layer 2): strip `<img>` whose `data-media-id` is not among `attachedMedia` ids — gallery attachments (role `inline` not embedded in body) intentionally untouched.
4. Story reader/preview render via existing `safe_body_html` (sanitizer now keeps valid imgs).

## 3. Context (Where)
- **Modify:** `app/Services/HtmlSanitizer.php`, `app/Livewire/Admin/AddNews.php`, `resources/js/wizard/wizard-main.js`, `tests/Unit/HtmlSanitizerTest.php`, `tests/Feature/AddNewsTest.php`
- **Reference:** archive modal flow (`wizard-main.js:861-942`), `toggleMedia`/`syncMedia`, storage policy (immutable CDN derivative URLs, AGENTS §6)

## 4. Prompt (For the Coding AI)
> Allow only library-backed images inside the editor body: sanitizer accepts `img` with `data-media-id` (else removes), archive modal gains an inline insert mode that registers the attachment and embeds at the cursor, autosave reconciles imgs against attachments. No base64, no foreign URLs, no migration (story_media role `inline` reused).

## 5. Test Criteria
- [ ] Sanitizer: img with data-media-id + safe src kept; img without data-media-id removed; on* attrs stripped
- [ ] Feature: `attachInline` idempotent (no toggle-off, no duplicates); autosave strips img with unattached media-id, keeps attached one
- [ ] `php -l` clean; full suite green

## 6. Completion Notes
- **Shipped:** Sanitizer `img` allowlist with mandatory `data-media-id` (base64/hotlink pastes dropped at layer 1); archive-modal inline mode (single-select, cursor captured pre-modal, "Insert inline" label); `attachInline()` idempotent registration (reuses story_media role `inline`, no migration); autosave layer-2 reconcile (unattached media-ids stripped; gallery attachments untouched); toolbar 🖼 now opens the archive modal instead of Quill's base64 handler.
- **Tests:** `php artisan test` — 949 passed / 6 skipped. New: 3 sanitizer unit tests (media-id kept/removed/foreign-attrs), 2 feature tests (idempotent attachInline, autosave reconcile).
- **Gates:** `php -l` clean · pint passed · `npm run build` ✓ (new JS bundle).
- **Live Smoke:** Pending manual browser confirmation (insert inline → publish → reader render).
- **Review:** —

## 7. Prompt Ready?
- [x] Yes
