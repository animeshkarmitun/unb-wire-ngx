# Remediation Plan v2 — Fix Everything (Post-Scaffold Failure)

**Date:** 2026-08-29 16:40
**Trigger:** User report: `Upload` dead, drag-drop dead, `+ New story` dead again, wizard nowhere near `app-data` — scaffold ✅ masked real gaps, Playwright was worthless (only ExampleTest).
**Owner:** Architect + Coder (no more batch ✅)

---

## 1. Root Cause (blunt)

1. **Scaffold-as-done:** I marked 18 M8 tasks ✅ after only token/chrome + quick wire (`M8-FOUND/WIZ-001`) and left the heavy interactions (upload, drop, wire toolbar, find bar, fullscreen, preview collapse/overlay, field intake reason modal, AI drawer/diff/gate) as "deferred polish" — direct violation of `app-data/README §5` where those interactions ARE the product.
2. **No interaction E2E:** `M8-E2E-001` was a stub. Only `Tests\Feature\ExampleTest` ran → never clicked `Upload`, never dragged a file, never typed in Quill. So "green CI" was meaningless.
3. **No live smoke per change:** `docs/workflow/live-test-runbook.md` (clear caches → hit routes as Admin/Editor/Uploader) was skipped when fixing `href="#"`. Hence `+ New story` broke again right after being fixed.
4. **Velocity over fidelity:** Batched 7 heavy wizard tasks into one "scaffold faithful" note to close the milestone fast. Business needs fidelity, not velocity.

## 2. Fix Scope — What "Fixed" Means Now

**Definition of Done = Prototype 1:1 + DB contract + RBAC + Live Smoke + Interaction E2E, or it stays 🔧.**

### Must-fix inventory (from app-data + live reports)

| Surface | Broken Now | Required (README §5 + FR) | Ticket |
|---------|------------|---------------------------|--------|
| `add-news.html` → `Livewire/AddNews` | `Upload` btn has no handler, drag-drop no overlay/progress, `+ New story` href fix regressed, Quill bare (no wire toolbar: dateline/pull-quote/table/signoff/cleanup/find&replace/rev-history), no find bar, no fullscreen, no featured 1200×630, no doc-import, no tags autocomplete, no seg-control preview | All of it, per NFR §14 | `M8-WIZ-001…006` reopened |
| `unb-photo-manager.html` → `Livewire/PhotoManager` | Same upload/drop dead (`<x-btn>Upload</x-btn>` inert), no progress toast, no justified grid hover-reveal, no inspector ZIP, field intake `approve` works but `reject/re-edit` no reason modal | Upload + drop + progress + inspector + intake reason modal | `M8-PHOTO-001/002` reopened |
| `english-news.html` → `Livewire/NewsList` | `+ New story` `href="#"` again, row → workflow drawer 409/take-over not asserted | Fix sweep + drawer 409 | `M8-NEWS-001/002` reopened |
| `index.html` → `admin/dashboard` | KPI already wired, but `View all`/`All clients` still `#` | Sweep all `href="#"` → real routes | `M8-DASH-001` |
| `roles.html` | System-role lock + member guard not E2E'd | Drawer + guard | `M8-SIMPLE-001` |
| `ai-settings.html` | Kill switch / budget not E2E'd | `localStorage('unb_ai_settings')` ↔ `settings` table bridge | `M8-SIMPLE-001` + `WIZ-006` |
| **Cross-cutting** | Empty tests, no live smoke | Exhaustive Playwright + live smoke gate | `M8-E2E-001` + `M8-QA-001` |

### Sweep: every `href="#"` in `resources/views/**` → real `route()` or removed (already grepped: 5 product files + 5 app-data references).

## 3. Execution Plan — No More Batch ✅

**Order is dependency-locked (README §11): foundation → simple → lists → DAM → wizard last. Within wizard, one task = one PR, one E2E, one live smoke.**

| Phase | Tasks (reopened) | Ships | Gate (must pass or stays 🔧) |
|-------|------------------|-------|------------------------------|
| **P0 Hotfix (today)** | `M8-PHOTO-HF` + `M8-WIZ-HF` | `PhotoManager` upload: `WithFileUploads`, hidden `<input type=file>` + `wire:model` + `MediaService::store` (MIME/size/credit), `drop-overlay` + `up-toasts` with bar/pct; `AddNews` same + doc-import `.docx` (mammoth.js) + `+ New story` sweep | Manual: click Upload → choose JPG → toast progress → appears in library; drag file onto page → overlay → upload; `php artisan test` + manual live smoke as Admin |
| **P1 Lists + DAM faithful** | `M8-NEWS-001/002`, `M8-PHOTO-001/002` | Tabs with counts, filter URL-sync, sticky header, pagination, row states + notes badge, drawer (status flow, takeover 409, notes thread), justified grid, field intake per-batch approve/reject/re-edit with **required reason modal** + Reverb badge | Playwright `news.spec.ts` + `photo.spec.ts` (see §4) |
| **P2 Wizard shell polish** | `M8-WIZ-001` | Already done (stepper lines + sticky nav) — add restore banner + embargo TZ test | `wizard-shell.spec.ts` |
| **P3 Wizard body** | `M8-WIZ-002` | Quill wire toolbar (dateline/pull-quote/table/signoff/cleanup/find&replace/rev-history) + find bar + fullscreen + word count + rev history diff | `wizard-editor.spec.ts` |
| **P4 Wizard media** | `M8-WIZ-003` | Featured 1200×630 has-photo, attach grid g1–g8 + rights + bulk bar + archive modal, doc-import modal, drop overlay + progress island `resources/js/media/drop-overlay.js` | `wizard-media.spec.ts` |
| **P5 Wizard meta** | `M8-WIZ-004` | Tags autocomplete (hash+uses), type chips, seg-control + distribution preview (`DistributionService::previewPlan`), live preview panel + collapse strip + overlay device toggles (760/600/392) `resources/js/preview/live-preview.js` | `wizard-meta.spec.ts` |
| **P6 Wizard workflow** | `M8-WIZ-005` | `wf-strip` + `nt-thread` + take-over/shift handover, immutable sys notes | `wizard-workflow.spec.ts` |
| **P7 AI desk** | `M8-WIZ-006` | Drawer + per-card apply + raw vs AI diff (word-level) + `✦ unreviewed` + new-facts warning + publish gate + kill switch + budget 429 | `wizard-ai.spec.ts` |
| **P8 E2E + QA** | `M8-E2E-001`, `M8-QA-001` | Full draft→review→rework→publish→fan-out→correction flow per FR-NWS-010, plus axe + Bangla NFC + N+1 `preventLazyLoading` | `e2e/wizard.spec.ts` + `axe` + `php artisan test` green, `migrate:fresh --seed` clean |

**Rule: One task per prompt, ≤3k tokens, single file focus. No "scaffold faithful" — either it's 1:1 or it's 🔧.**

## 4. Why E2E Will Not Be Worthless This Time

**Old E2E:** 2 ExampleTests (never opened a page). **New E2E (Playwright, per prototype contract):**

```ts
// tests/e2e/photo.spec.ts
test('PHOTO-001 upload + drag-drop', async ({ page }) => {
  await login(page, 'Admin');
  await page.goto('/admin/photos');
  // click Upload → file chooser → assert toast → assert grid
  const fileChooser = page.waitForEvent('filechooser');
  await page.getByRole('button', {name: 'Upload'}).click();
  (await fileChooser).setFiles('fixtures/sample.jpg');
  await expect(page.locator('.up-toast')).toContainText('Uploading');
  await expect(page.locator('.attach-tile').first()).toBeVisible({timeout: 15000});

  // drag-drop
  await page.dispatchEvent('body', 'dragover', {dataTransfer: {files: []}});
  await expect(page.locator('.drop-overlay')).toBeVisible();
  // ... drop file, assert intake badge bump
});

// tests/e2e/wizard.spec.ts — every step
// tests/e2e/news.spec.ts — tabs counts, filter sync, drawer 409, notes
```

Coverage checklist (README §5 verbatim):
- [ ] Add News: autosave/restore banner, wire toolbar (each button), find bar, fullscreen, revision history, AI drawer/compare/gate/touched markers, featured 1200×630, attach bulk/ZIP, doc import, type chips, seg-control, distribution preview, wf-strip takeover, notes thread replies, live preview collapse + device overlay
- [ ] News list: tabs counts, pagination, owner line + notes badge, drawer open 409, takeover
- [ ] Photo: workflow tabs + alert counts, justified grid, hover select, inspector, bulk bar, ZIP, field intake per-batch/per-photo approve/reject/re-edit + reason required (422), Reverb badge
- [ ] RBAC: Editor can publish, Uploader gets 403, Admin all
- [ ] Live smoke: `php artisan config:clear && cache:clear && view:clear && route:clear` → hit every admin route as 3 roles → no 500, no 419

Every PR runs: `php -l`, `php artisan test`, `npm run build`, `npx playwright test` — any fail = block.

## 5. Immediate Next Actions (no asks)

1. Reset board: flip `M8-PHOTO-001/002`, `M8-WIZ-002…006`, `M8-E2E-001`, `M8-QA-001` to 🔧 (done in next step).
2. Hotfix `PhotoManager` + `AddNews` upload/drop (P0) — ship tonight, live smoke recorded.
3. Sweep all `href="#"` → routes (already grepped).
4. Build `tests/e2e/photo.spec.ts` + `wizard.spec.ts` skeleton so next PR has real coverage.

## 6. Acceptance

This plan is done when `M8-QA-001` passes axe + Bangla fixture + `preventLazyLoading` + `migrate:fresh --seed` + all Playwright specs green + `docs/knowledge-inventory/*` synced. Until then, every Monday standup starts with the board red.
