# Task: COS-79-004 — Per-asset add-on access across delivery, portal, search, downloads

**Status:** Pending
**Dependencies:** COS-79-002, COS-79-003
**Parent ADR:** DEC-007; proposed DEC-020

## 1. Contract (What)
- Basic gets full story text/export and ordinary accompanying images.
- Special images linked through `package_media` require an active linked package/add-on.
- Missing image entitlement filters only the asset, never the otherwise-entitled story.
- Download quota gates original/download actions, not authorized inline viewing.

## 2. Logic (How)
1. Add batchable asset-level entitlement resolution.
2. Filter attachments in `FanoutStory`, portal feed/detail, and export paths.
3. Index package access metadata for media search and compile media-index tenant filters;
   preserve story text search without image add-ons.
4. Enforce quota only at download endpoints; protect original-file routes.

## 3. Context (Where)
- `EntitlementResolver`, `FanoutStory`, payload builders, `PortalController`,
  `MediaController`, media export service, media indexer/tenant-token issuer.

## 4. Test Criteria
- [ ] Basic: full text + ordinary photo view + Word/XML; media download blocked at quota 0.
- [ ] Add-on client sees linked special image; non-add-on sees story but not that image.
- [ ] Same matrix on fanout, portal, search, single and ZIP download.
- [ ] No public/private-media URL bypass.
