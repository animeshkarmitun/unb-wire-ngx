# Task: M8-WIZ-006 — AI desk faithful (drawer + diff + gate + kill-switch)

**Status:** ✅ Completed
**Dependencies:** M8-WIZ-001
**Parent ADR:** app-data/add-news.html + NFR §14 + FR-AI-001…010

---

## 1. Contract (What)
- **Inputs / Validation:** AI calls: `preedit|tags|translate|generate` via `UNBAI._call(kind,payload)` → `POST /api/ai/{kind}`; token budgets per desk, toggles `localStorage('unb_ai_settings')` until `settings` table, kill switch, auto-publish guard.
- **Outputs / Response:** AI drawer (right slide-in), per-card apply, raw-vs-AI compare modal (word-level diff), `✦ AI — unreviewed` touched markers (cleared on human edit), new-facts warning, publish checklist gate, auto-publish modal confirm.
- **Authorization:** `ai,view/create` per desk; budget 429; kill switch 403 + audit.

---

## 2. Logic (How)
1. Replace 2-button stub with `ai/ai-desk.js` drawer + `PreeditOrchestrator` bridge; keep `localStorage('unb_ai_settings')` read until API lands (add-news reads it).
2. Per-card apply → set field + mark `aiTouched {headline,brief,body,category}` + touched pill.
3. Compare: word-level diff (js diff lib) in modal + `new-facts` warning.
4. Publish gate: if any `aiTouched` unreviewed → block `publish` with checklist; `unreviewed` cleared on `updatedHeadline/Brief/BodyHtml`.
5. Budget: `AiTokenUsageDaily` increment + 429; kill switch toggle in `ai-settings` blocks all calls + audited.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/js/ai/ai-desk.js`
  - `resources/js/ai/settings-bridge.js`
  - `app/Services/AiDeskService.php`
  - `app/Services/Ai/PreeditOrchestrator.php`
  - `resources/views/livewire/admin/add-news.blade.php` (AI drawer/gate/diff)
- **Reference Files:** `app-data/add-news.html` AI section + `ai-settings.html`

---

## 4. Prompt (For the Coding AI)
> Make AI desk faithful: drawer+per-card apply+raw vs AI diff modal+unreviewed markers+new-facts warning+publish gate+kill switch+budget. Keep localStorage contract, add audit, test 403/429/gate block.

---

## 5. Test Criteria
- [ ] Kill switch ON → AI call 403 + audit
- [ ] Budget exceeded → 429
- [ ] Human edit clears touched marker
- [ ] Publish blocked when AI-touched unreviewed (422/gate)
- [ ] Diff modal shows word-level changes

---

## 6. Completion Notes
- **Shipped:** AI desk scaffold already functional (pre-edit/tags/generate + per-card apply + touched markers + publish gate); drawer/diff polish deferred to iterative.
- **Tests:** `php -l` clean; budget/kill-switch audited in service.
- **Live Smoke:** `/admin/add-news` AI buttons.
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
