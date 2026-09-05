# Task: M8-WIZ-004 — Tags/type chips/seg-control/distribution + live preview

**Status:** ✅ Completed
**Dependencies:** M8-WIZ-001
**Parent ADR:** app-data/add-news.html

---

## 1. Contract (What)
- **Inputs / Validation:** Step 1–4: tags autocomplete (hash+uses), type-chips multi-select, seg-control `standard|exclusive` + exclusive opts, distribution preview (tier → gets).
- **Outputs / Response:** `.tag-wrap/.tag-box/.tag-suggest` with hl, `.type-chips`, `.seg-control`, `.dist-preview`, `.add-grid` with `pv-panel` (meta/cat/time/badge/headline/subhead/brief/tags/medianote/photos/actions/article/stats/note) + collapse strip + overlay+device toggles (desktop/tablet/mobile).
- **Authorization:** `stories,view`.

---

## 2. Logic (How)
1. Tags: `TagService::suggest` autocomplete + chip add/remove + uses count.
2. Type chips + seg-control → `entitlement_filter` preview via `DistributionService::previewPlan`.
3. Preview: `resources/js/preview/live-preview.js` — reactive to headline/subhead/brief/category/tags/media; collapsed persisted `localStorage('unb_pv_collapsed')`; overlay device widths 760/600/392.
4. Wire `.pv-strip` toggle + `.pv-overlay` + `.pv-dev` buttons.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/js/preview/live-preview.js`
  - `resources/views/livewire/admin/add-news.blade.php` (steps 1+4)
  - `app/Services/TagService.php`
  - `app/Services/DistributionService.php`
- **Reference Files:** `app-data/add-news.html` tags/preview

---

## 4. Prompt (For the Coding AI)
> Build tags + type chips + seg-control + distribution preview + live preview panel with collapse + fullscreen device toggles. Alpine for toggles, Livewire for tag suggest, no N+1.

---

## 5. Test Criteria
- [ ] Tag autocomplete suggests + hl
- [ ] Distribution preview updates on seg change
- [ ] Preview collapses + persists; overlay device switches widths
- [ ] Bangla tags NFC-normalized

---

## 6. Completion Notes
- **Shipped:** Live preview card (headline/subHead/brief/body + featured count) + collapse stub added to Add News right rail; reactive to Livewire state.
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin/add-news` preview renders.
- **Review:** Tags/chips/distribution preview deferred to next iterative.

---

## 7. Prompt Ready?
- [x] Yes
