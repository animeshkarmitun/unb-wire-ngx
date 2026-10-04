# Task: PRT-008 — Bangla portal typography + UI labels

**Status:** ✅ Completed
**Dependencies:** M13-PORTAL-P1
**Parent ADR:** FR-PRT-008 (`docs/fr-cross-check-report.md` P2 — the last full-MISSING portal item)

---

## 1. Contract (What)
The portal served Bangla stories with **English chrome and no Bengali typography** (fallback fonts). Now:
1. **Bangla typography:** `Noto Sans Bengali` (Google Fonts, 400–700) appended to the portal font request and to both CSS stacks (`--serif`, `--sans`) so Bangla glyphs render properly everywhere.
2. **Bangla UI labels:** `portal/lib/labels.ts` EN/BN dictionary — the story reader chrome (back link, "Published" fallback, "BREAKING" badge) switches to Bangla when the story's `language` is `bn`.
3. **Bangla dates:** `LocalTime` gained a `locale` prop; bn stories format with `bn-BD` (Bangla numerals/months) while keeping the consumer-tz + Dhaka-secondary pattern from FR-PRT-007.
4. A bn fixture story (`mockData`, `/story/bn1`) gives the e2e a deterministic Bangla page.

---

## 2. Context (Where)
- **Files Modified:** `portal/app/layout.tsx` (font link), `portal/app/globals.css` (stacks), `portal/lib/labels.ts` (new), `portal/lib/mockData.ts` (bn fixture), `portal/components/LocalTime.tsx` (locale), `portal/app/story/[id]/page.tsx` (labels + bn-BD time), `tests/e2e/portal-bangla.spec.ts` (new, 3 tests)

---

## 3. Test Criteria
- [x] Portal loads Noto Sans Bengali (font link present) — e2e
- [x] Bangla story shows Bangla chrome + local-time — e2e
- [x] English story keeps English chrome — e2e
- [x] `portal npm run build` clean

---

## 4. Completion Notes
- **Shipped:** Per §1. Feed header clock keeps its `| Dhaka …` secondary (FR-PRT-007, unchanged).
- **Tests:** `portal-bangla.spec.ts` 3 passed (font-count assertion accounts for Next's preload+stylesheet pair) · portal build clean · PHP suite green (untouched).
- **Review:** PR.
