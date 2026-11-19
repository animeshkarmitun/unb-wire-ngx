# COS-79 Design: package quotas, client overrides, and per-asset add-ons

**Status:** Accepted for implementation per owner direction (2026-11-19)
**Related:** DEC-007; COS-79; COS-25/R2 explicitly deferred

## Contract

- Basic: full story text + Word/XML export + accompanying images viewable.
- Media download quota is separate from image viewing. Basic's initial default
  `media_quota=0` means no original download, not no image visibility.
- Special/story-extra images are linked to an add-on package through the existing
  `package_media` pivot. Clients without that active add-on cannot view/download those
  assets; story text itself remains visible if its base entitlement allows it.
- Initial editable defaults (existing onboarding rules): Premium 2000 stories / 1000
  media; Standard 800 / 400; Basic 200 / 0. System Admin can change them.
- Quota source: package-level defaults + explicit per-client overrides. An override
  replaces the effective package quota for that dimension.
- Quota semantics: `0` = no quota/downloads allowed; `null` = unlimited. Override
  presence is stored separately so "inherit" differs from explicit unlimited.
- Add-ons grant asset/package access; they do not silently increase base quotas.
- Only System Admin may mutate/delete packages, quotas, add-on grants, or client quota
  overrides. Other staff may view packages. Any package referenced by `client_packages`
  cannot be hard-deleted; archive is the safe path.
- Preserve current calendar-month usage calculation for this delivery (sent story
  deliveries + story downloads; media downloads). Changing quota counting semantics is
  a separate product decision.

## Effective rules

1. Resolve active base packages and active add-ons for the client's subscription window.
2. Union languages, categories, and package-level asset access.
3. Base-package quotas union by maximum; unlimited (`null`) on any active base package
   means unlimited for that dimension.
4. If the client has an explicit stories/media override, it replaces the union result;
   `0` and `null` are both valid explicit values.
5. A media asset with no `package_media` rows is governed by effective `media_kinds`.
   An asset linked to package(s) requires an active subscription to at least one linked
   package (and matching media kind).
6. Story visibility is not denied merely because its media is unavailable: filter the
   story's attachments independently in fanout, portal payloads, and search.
7. Media viewing and downloading are distinct: package/add-on entitlement gates whether
   an asset can be viewed; `media_quota` gates original/download actions.

## Schema changes

- `packages.stories_quota` / `packages.media_quota`: nullable integer, null = unlimited.
- `client_entitlement_overrides`: one row per client; boolean override-presence flags +
  nullable integer quota values (inherit / numeric / explicit unlimited).
- Existing `clients.notes.tier_quotas` values migrate into explicit override rows;
  application reads stop using the notes field afterward.
- `entitlement_filter` remains for languages/categories/media kinds/add-on marker; no
  free-text quota parsing.

## Rollout

Migration/backfill → resolver + tests → System Admin controls → per-asset enforcement
across delivery/portal/search/download → surface parity → live entitlement matrix smoke.
Use reversible migrations and PostgreSQL live verification.
