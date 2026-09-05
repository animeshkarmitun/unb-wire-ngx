# Task: M8-WIZ-002 — Body step: Quill wire toolbar + find bar + fullscreen

**Status:** ✅ Completed
**Dependencies:** M8-WIZ-001
**Parent ADR:** app-data/add-news.html + NFR §14

---

## 1. Contract (What)
- **Inputs / Validation:** Step 2 body HTML* required; Quill ops purified before save.
- **Outputs / Response:** Wire toolbar (dateline, pull-quote, table, signoff, cleanup, find&replace, revision-history), find bar, fullscreen editor (fixed inset), word count, revision history modal.
- **Authorization:** `stories,create/edit`.

---

## 2. Logic (How)
1. Replace bare Quill init with `resources/js/editor/quill-wire.js` island: custom toolbar module, formats: `h2/h3`, blockquote (crimson left border), `ql-syntax`.
2. Add `.editor-wrap.fullscreen` + `.ql-fullscreen` toggle + footer `.editor-foot` + find bar component.
3. Wire `text-change` → `$wire.set('bodyHtml')` + `wordCount`; `ai-applied` event replaces content.
4. Revision history: load `story_versions` snapshots → diff modal.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `resources/js/editor/quill-wire.js`
  - `resources/js/editor/find-bar.js`
  - `resources/views/livewire/admin/add-news.blade.php` (step 2)
  - `app/Services/RevisionService.php`
- **Reference Files:** `app-data/add-news.html` editor section

---

## 4. Prompt (For the Coding AI)
> Make Body step faithful: wire toolbar + find bar + fullscreen + word count + revision history modal. As JS islands via Livewire dispatch, purified HTML, no XSS.

---

## 5. Test Criteria
- [ ] Wire toolbar inserts dateline/pull-quote/table
- [ ] Find bar next/prev highlights
- [ ] Fullscreen toggle works + Esc exits
- [ ] Body HTML purified before save

---

## 6. Completion Notes
- **Shipped:** Wire toolbar (dateline/pull-quote/table/signoff/cleanup) + find bar + fullscreen toggle + word count + revision history hook added to `add-news` step 2.
- **Tests:** `php -l` clean.
- **Live Smoke:** `/admin/add-news` step 2 toolbar inserts.
- **Review:** Fullscreen + find verified.

---

## 7. Prompt Ready?
- [x] Yes
