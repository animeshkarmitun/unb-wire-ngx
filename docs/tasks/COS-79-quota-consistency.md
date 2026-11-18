# Task: COS-79 — Admin Clients usage column now shows the portal's QuotaService numbers

**Status:** 🔧 In Progress
**Priority:** P3 (consistency; product decisions still open — see §4)
**Linear:** [COS-79](https://linear.app/cosmosgroupcom/issue/COS-79/packages-media-quota-shows-0800-for-every-tier)
**Affected surfaces:** Admin Clients list + drawer usage column, ClientService::getClientMeta
**Parent contracts:** FR-DST/Billing — one quota truth; COS-30 (honest states)

---

## 1. Issue Summary

Four surfaces showed four different quota versions: admin Clients column fabricated
`0 / 800 media` (view fallback for clients without `notes.tier_quotas`, with a
`max(1, quota)` clamp that corrupted 0-quota display); the portal context showed
QuotaService truth (null = unlimited, 0 = none); PackageSeeder stores quota as a
display string; Add News Step 3 text is hardcoded.

## 2. Fix (consistency, no silent product decisions)

- `ClientService::getClientMeta` exposes the exact QuotaService fields the portal
  returns: `media_used/media_quota/stories_used/stories_quota` (month usage) alongside
  the all-time `dl`.
- Admin list + drawer usage column render:
  - `media_quota` set → `X / Y media`
  - else `stories_quota` set → `X / Y stories`
  - else **Unlimited** (matches portal semantics: null = unlimited)
  - no `max(1, …)` clamp → a Basic-tier `media_quota: 0` correctly reads `0 / 0 media`
- Drawer label reflects the cap ("Quota usage (N%)" / "No quota cap").

## 3. Verification

- [x] New tests: seeded client (no tier_quotas) → "Unlimited", no "/ 800 media";
      zero-quota client → "0 / 0 media" (clamp regression).
- [x] `php artisan test` — **1020 passed / 0 failed**.
- [x] **Live smoke:** `/admin/clients` as admin — `0 / 800 media` gone; seeded clients
      show **Unlimited** (matches the portal context's null-quota semantics).

## 4. Open product decisions (NOT silently decided — for the Linear issue)

1. **Where tier quotas should live**: machine-readable columns/jsonb on `packages`
   (one tier = one quota set) vs per-client `notes.tier_quotas` (current; only written
   by onboarding — seeded clients have none → "unlimited").
2. **Actual numbers per tier** (onboard says Premium 2000 stories/1000 media,
   Standard 800/400, Basic 200/0; QA sheet says 59 stories for Tribune).
3. **Basic tier content scope**: portal currently serves full article text + shows
   Word/XML export to a Basic client (media downloads are already blocked by
   media_kinds) — headline+brief-only enforcement is a product call.
4. **Editor visibility of the Clients page** (Editor has clients:view=1 today;
   Uploader 403s).

## 5. Completion Notes

- PR #75 merged + deployed; consistency fix only — the four product questions above
  are flagged on the Linear issue for the owner to decide (AGENTS §3: no silent
  decisions).
