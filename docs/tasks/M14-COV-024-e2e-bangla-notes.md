# Task: M14-COV-024 — E2E Bangla publish and notes isolation

**Status:** ⏳ Pending
**Dependencies:** M14-COV-001
**Parent ADR:** DEC-007; FR-NWS-009, FR-NWS-012

---

## 1. Contract (What)
- **Inputs / Validation:** Wizard language `bn`, Bangla headline, internal note text `NEWSROOM-NOTE-UNIQUE`.
- **Outputs / Response:** `/admin/news/bn` shows the headline. Client feed `language=bn` contains it. Feed JSON and portal story JSON do not contain `NEWSROOM-NOTE-UNIQUE`. English feed does not contain it if the package is bn-only; if the seed package includes both, assert `language` field is `bn`.
- **Authorization:** Admin author. Client API key.

---

## 2. Logic (How)
1. New `tests/e2e/bangla-publish.spec.ts`. Do not use `/story/bn1`.
2. Create via the wizard `setLanguage('bn')` control, publish, assert list + API.
3. Note is added before publish. Client payload assert is a string absence on the JSON body.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `tests/e2e/bangla-publish.spec.ts`
- **Reference Files:**
  - `app/Livewire/Admin/AddNews.php`
  - `app/Http/Controllers/Api/ClientFeedController.php`

---

## 4. Prompt (For the Coding AI)
> Publish a Bangla story through the wizard. Assert it on `/admin/news/bn` and the client feed with `language=bn`. Assert an internal note string is absent from the client JSON. Do not use mock id `bn1`. Unique headline.

---

## 5. Test Criteria
- [ ] BN list shows the headline
- [ ] Feed item language is bn
- [ ] Note string absent from client JSON
- [ ] Spec green

---

## 6. Completion Notes
- **Shipped:** New `tests/e2e/bangla-publish.spec.ts` publishes a Bangla story (`language=bn`) through the wizard, adds an internal note with a unique string `NEWSROOM-NOTE-UNIQUE-{ts}`, then asserts: `/admin/news/bn` shows the headline; `GET /api/v1/portal/feed?language=bn&limit=50` contains a Bangla story with that headline; the JSON of that feed and `GET /api/v1/portal/story/{publicId}` does **not** contain `NEWSROOM-NOTE-UNIQUE-{ts}`; `StoryNote::where('body', $note)->count() === 1` confirms the note was saved server-side. Depends on COV-001 (isolation).
- **Tests:** not run in this environment.
- **Live Smoke:** CI Playwright run.
- **Review:**

---

## 7. Prompt Ready?
- [x] Yes
