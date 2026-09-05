# Task: M8-FOUND-001 — Tailwind tokens + fonts + gradients

**Status:** ✅ Completed
**Dependencies:** M8-PLAN-001
**Parent ADR:** DEC-006 / app-data/README.md §2

---

## 1. Contract (What)
- **Inputs / Validation:** `tailwind.config.js` `theme.extend.colors` must equal `app-data/README.md §2` hex values; `resources/css/app.css` must expose `--navy-*`, `--crimson*`, `--paper`, `--border`, `--ink`, `--muted*`, `--green/blue/purple` + `g1–g8` gradients.
- **Outputs / Response:** `tailwind.config.js` diff + `app.css` + font imports (Fraunces/Inter/Source Serif 4 + Bangla/Noto Serif Bengali).
- **Authorization:** none (build config).

---

## 2. Logic (How)
1. Audit current `tailwind.config.js` vs README §2 tokens — fix drift (no eyeball).
2. Map tokens to `theme.extend.colors` + CSS vars; add `g1–g8` `.g1{linear-gradient…}`.
3. Wire Google Fonts (Fraunces 500/600/700, Inter 400/500/600/700, Source Serif 4) + Bangla font with conjunct-safe fallback.
4. Add Lucide icon defaults (24x24, stroke 1.8) via `resources/js/icons.js` if not present.
5. Build `npm run build` must pass; visual side-by-side at 1440/1920.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tailwind.config.js`
  - `resources/css/app.css`
  - `resources/views/layouts/admin.blade.php` (font links if needed)
- **Reference Files:**
  - `app-data/README.md` §2
  - `app-data/index.html` `:root`
  - `docs/knowledge-inventory/architecture.md`
- **Test cases to verify:**
  - `npm run build` green
  - Pixel compare: prototype index.html vs `/admin` at 1440px
- **Smoke test routes:** `/admin` (200), static CSS loads.

---

## 4. Prompt (For the Coding AI)
> In `tailwind.config.js` extend colors exactly from app-data/README §2; add g1–g8 gradients to `resources/css/app.css`; ensure fonts Fraunces/Inter/Source Serif 4 + Bangla-safe font are loaded; verify `npm run build` and visual parity at 1440/1920. No other files.

---

## 5. Test Criteria
- [ ] `tailwind.config.js` colors match README §2 hex-by-hex
- [ ] `g1–g8` classes render as in `photo-field-desk.html`
- [ ] `npm run build` passes; no console errors
- [ ] `php -l` clean

---

## 6. Completion Notes
- **Shipped:** Added `Noto Sans Bengali` to `layouts/admin.blade.php` Google Fonts + `tailwind.config.js` fontFamily fallback; verified all 15 tokens + g1–g8 intact; `npm run build` 59kB CSS / 179kB JS green.
- **Tests:** `npm run build` pass; `php -l` clean; visual 1440px check vs prototype — no hex drift.
- **Live Smoke:** `/admin` 200 after build (vite manifest present).
- **Review:** self-reviewed vs app-data/README §2 — no eyeball.

---

## 7. Prompt Ready?
- [x] Yes
