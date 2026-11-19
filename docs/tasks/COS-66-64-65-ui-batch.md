# Task: COS-66/64/65 — small UI batch: human 403 page, field-app placeholders, RBAC sidebar

**Status:** 🔧 In Progress
**Priority:** P4/P3/P4
**Linear:** [COS-66](https://linear.app/cosmosgroupcom/issue/COS-66/ui-forbidden-page-shows-developer-text-need-view) · [COS-64](https://linear.app/cosmosgroupcom/issue/COS-64/ui-field-apps-links-mojo-desk-photo-desk-do) · [COS-65](https://linear.app/cosmosgroupcom/issue/COS-65/role-sidebar-shows-pages-the-role-cannot-open-uploader-editor)
**Affected surfaces:** `errors/403.blade.php` (new), sidebar component

---

## 1. Fixes

- **COS-66**: new `resources/views/errors/403.blade.php` (UNB-styled, matching the 419
  page) — "You don't have access to this page. Contact your administrator…". The
  developer wording ("need view on settings") and internal permission names are gone.
- **COS-64**: MoJo desk / Photo desk sidebar links rendered as disabled placeholders with
  a "soon" badge (they were dead `href="#"`). Per DEC-008 field apps are deferred —
  placeholders are honest.
- **COS-65**: the whole sidebar now mirrors server-side RBAC via a `$sideCan` helper —
  Content pipeline (stories/stories_bn), Clients, Distribution log, Client FTP,
  Packages, Roles & access, AI settings all hidden without the respective `view`
  permission (News/Service/Audit were already done in COS-63).

## 2. Verification

- [x] `php artisan test` — **1029 passed / 0 failed**; pint clean.
- [x] **Live smoke:** uploader (enamul) sidebar — Packages/Clients/Distribution/Roles/AI
      links hidden, Field apps show "soon" placeholders, dashboard business widgets stay
      hidden (COS-80); `/admin/packages` → styled 403 with friendly copy, no developer
      wording or permission names.

## 3. Completion Notes

- PR #83 merged + deployed.
