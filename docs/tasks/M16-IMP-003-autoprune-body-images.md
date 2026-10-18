# Task: M16-IMP-003 — Auto-prune body-embedded images (role `body`)

**Status:** ✅ Completed
**Dependencies:** M16-IMP-002
**Parent ADR:** Inline-images brainstorm Phase 2 (approved: editor-deleted inline images auto-prune; gallery attachments explicitly protected)

---

## 1. Contract (What)
- **Inputs / Validation:** story_media.role gains `body` (migration widens the pgsql CHECK); `attachInline()` registers with role `body` and upgrades existing attachments to `body` when embedded.
- **Outputs / Response:** artist-free autosave reconcile: role-`body` attachments not referenced in `body_html` are pruned (pivot rebuilt via `syncMedia`); role `body` excluded from client-facing gallery lists (payload still carries the image via `body_html`).
- **Authorization:** unchanged surface.

## 2. Logic (How)
1. Migration: drop + recreate `story_media_role_check` with `('featured','inline','body')`; down() deletes `body` rows and restores the original check.
2. `StoryRepository::syncMedia`: role pass-through (`body` respected, anything else non-featured stays `inline`).
3. `AddNews::attachInline`: role `body`; idempotent — existing selected id upgrades its role to `body` (embedding a gallery image into the body adopts it).
4. `autosave()` reconcile order: strip imgs with unattached ids → collect body ids → prune role-`body` attachments absent from body → `syncMedia` persists the remainder.
5. JS inline insert reordered: `insertEmbed` + `syncBody` FIRST, then `attachInline` — otherwise the prune would eat the fresh registration before its embed syncs.
6. Excludes role `body` from gallery-ish lists: `PortalController::formatStoryList` (`media`, `caps`) + `WebhookPayloadBuilder` (payload keeps `body_html`).
7. Docs sync: `app-data/v1-database-design.md` story_media role values + `docs/knowledge-inventory/data-model.md`.

## 3. Context (Where)
- **Create:** `database/migrations/2026_10_18_000001_alter_story_media_role_check_include_body.php`
- **Modify:** `app/Repositories/StoryRepository.php`, `app/Livewire/Admin/AddNews.php`, `resources/js/wizard/wizard-main.js`, `app/Http/Controllers/Api/PortalController.php`, `app/Services/Delivery/WebhookPayloadBuilder.php`, docs + tests
- **Reference:** `story_media` CHECK (migration `2026_08_27_000030`), schema-parity checker (no role pinning)

## 4. Prompt (For the Coding AI)
> Distinguish editor-embedded images from gallery attachments: new `body` role in story_media (CHECK widened via migration), autosave prunes body-role attachments whose embed is gone, gallery/payload lists filter body-role out (body_html still carries them). Reorder the JS insert to embed before registering.

## 5. Test Criteria
- [ ] Migration runs (fresh) and widens the check; down() restores
- [ ] autosave prunes a role-`body` attachment after its img is removed from the body; gallery `inline` attachments survive
- [ ] Webhook payload media list excludes role `body`
- [ ] `php artisan test` green; schema-parity-check passes

## 6. Completion Notes
- **Shipped:** Migration widening `story_media_role_check` to `('featured','inline','body')`; `syncMedia` role pass-through; `attachInline` now role `body` + adopts already-attached assets into the body (featured protected); autosave prunes body-role attachments whose embed left the text (JS insert reordered to embed-before-register); `body` filtered out of portal media/caps + webhook payload media list (images still delivered via `body_html`); docs synced (`v1-database-design.md`, `data-model.md`).
- **Tests:** `php artisan test` — 954 passed / 6 skipped. New: prune test, gallery-survives test, webhook payload exclusion test; idempotency test updated to the embed-first sequence. `migrate:fresh --seed` ✓ · schema-parity ✓ (no checker changes needed).
- **Gates:** `php -l` · pint passed · `npm run build` ✓.
- **Live Smoke:** Pending manual browser confirmation (insert → delete in editor → attachment auto-gone).
- **Review:** —

## 7. Prompt Ready?
- [x] Yes
