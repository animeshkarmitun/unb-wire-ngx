# Task: COS-63 — Bangla Desk uploader can work in Bangla News (language-aware RBAC)

**Status:** 🔧 In Progress
**Priority:** P2 (Bangla desk blocked from its core job)
**Linear:** [COS-63](https://linear.app/cosmosgroupcom/issue/COS-63/role-bangla-desk-uploader-has-no-access-to-bangla-news)
**Affected surfaces:** `/admin/news/{lang}`, `/admin/service/{lang}`, `/admin/story/{id}`, AddNews wizard, NewsList, StoryView, sidebar
**Parent contracts:** Roles & access matrix (`stories_bn` module exists in RoleSeeder + RolesManager)

---

## 1. Issue Summary

Uploader-Bangla (stories: none, stories_bn: view/create/edit) got **403 "need view on
stories"** on both English and Bangla News — the routes/permission checks only ever
consulted the `stories` module. The `stories_bn` module existed in the role matrix but was
**never wired into any enforcement point**. Uploader-English saw both lists (stories:view
unlocked both) — unintended cross-desk access.

## 2. Fix: language-aware RBAC wiring

- `RbacService::storiesModuleFor(string $language)` — single resolver
  (`bn → stories_bn`, else `stories`), used by every language-aware check.
- Routes: `/admin/news/{lang}` and `/admin/service/{lang}` gated by the language module
  (closure check); `/admin/story/{publicId}` gated by the **story's** language;
  `/admin/add-news` gate moved into `AddNews::mount` (route middleware cannot know the
  language): new story → `create` on the selected language module; existing story →
  `edit` on the story's language module.
- `AddNews`: all internal asserts (autosave, send-to-review, needs-work, publish,
  add-note, doc-import, restore-version) use the wizard's language module.
- `NewsList`: take-over / toggle-publish / delete gated per story language; bulk
  publish/delete by the list's language.
- `StoryView`: edit-capability computed per story language; restore / note / status
  transitions gated per story language.
- Sidebar: English/Bangla News + Service links shown only when the role has the
  respective module view (partial fix for COS-65 — other sidebar items tracked there).

## 3. Verification Plan

- [x] RbacEndpointTest: `/admin/news/bn` + `/admin/service/bn` 403 for Uploader-English,
      200 for Uploader-Bangla; `/admin/news/en` 403 for Uploader-Bangla; sidebar shows
      only the uploader's language.
- [x] `php artisan test` — **1013 passed / 0 failed**; schema parity PASSED.
- [ ] Live smoke as `tanvir@unbnews.org` (Uploader-Bangla) + `enamul@unbnews.org`.

## 4. Completion Notes

_(pending)_
