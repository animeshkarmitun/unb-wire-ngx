# Task: COS-78 — Story briefs: sentence-boundary builder + data repair

**Status:** 🔧 In Progress
**Priority:** P3 (bad data reaches clients in portal + exports)
**Linear:** [COS-78](https://linear.app/cosmosgroupcom/issue/COS-78/data-story-briefs-are-cut-mid-word-and-contain)
**Affected surfaces:** `BriefBuilder` (new), AddNews doc-import, `stories:repair-briefs` (new)
**Parent contracts:** Add News form — "the brief must stand on its own without the body text"

---

## 1. Issue Summary

Imported briefs were cut from the body at a fixed length (`Str::limit(…, 280, '')`):
mid-word cuts ("frontli"), `\r\n` inside the brief, no ellipsis. The bad text is stored,
so clients receive it in the portal feed and Word/XML exports.

## 2. Fix

- **`App\Support\BriefBuilder::fromPlainText(text, limit=280)`**: normalises newlines
  and double spaces; cuts on the last **sentence boundary** (incl. Bangla `।`) within
  the budget; otherwise a **word boundary** with an ellipsis — never mid-word.
- **AddNews doc-import** uses it instead of the raw `Str::limit`.
- **`php artisan stories:repair-briefs`** (new, `--dry-run`): repairs stored briefs that
  carry the artefacts (newlines or exact-length cuts) using the same builder.

## 3. Verification

- [x] `BriefBuilderTest` (4 unit tests) + `BriefStoryFlowTest` (doc-import + repair command).
- [x] `php artisan test` — **1026 passed / 0 failed**.
- [ ] Live: run `stories:repair-briefs` on the test server; verify the reported story's brief.

## 4. Related (flagged, not implemented — product decision)

- `is_breaking` stays `true` days after publishing — should it auto-clear after N hours?
  Flagged on the Linear issue (needs a product rule, e.g. auto-clear after 6–12h via scheduler).
