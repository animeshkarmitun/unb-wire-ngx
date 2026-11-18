# Task: COS-75 — Distribution log empty: media_kinds gate blocked every story; fake 98.4% dashboard rate

**Status:** 🔧 In Progress
**Priority:** P2 (distribution pipeline effectively dead for real packages)
**Linear:** [COS-75](https://linear.app/cosmosgroupcom/issue/COS-75/dist-distribution-log-is-empty-although-clients-have)
**Affected surfaces:** `EntitlementResolver::clientAllowed`, `FanoutStory` (api channels), Dashboard success-rate card
**Parent contracts:** FR-DST-002 (one entitlement definition drives fan-out + portal + search), NFR (honest states — COS-30)

---

## 1. Issue Summary

Distribution log showed 0 deliveries although clients had active channels and 16 active
subscriptions; the dashboard showed a fabricated "98.4%" rate. Horizon **was** running
(fanout workers alive) — the pipeline itself was silently matching zero clients.

## 2. Root Causes (live-verified)

1. **`clientAllowed` media_kinds gate**: every real package (Premium/Standard) declares
   `media_kinds: ["photo","video"]` / `["photo"]`. The check required the story's media
   kinds to intersect — so a **story with no media was denied to everyone**. Text-only
   wire stories (the majority) could never fan out. Live probe: all active clients
   `allowed=no` for story 77 (no media), `allowed=yes` only for the media_kinds-less
   Tribune package.
2. **`api` channels did nothing**: the deliveries row was inserted (`queued`) but no
   processor exists for pull-based channels — rows would pile up as eternally queued.
3. **Dashboard fallback faked rates**: no deliveries → hardcoded `98.4 / 98.7` and a
   fabricated delta.

## 3. Fix

- `EntitlementResolver::clientAllowed()`: media_kinds only gates when the story **has**
  media; a media-less story has nothing to filter and is delivered (still denied when
  the story's kinds don't intersect, e.g. video-only story for a photo package —
  unchanged semantics).
- `FanoutStory`: `api` channels are pull-based (`/api/v1/feed` with scoped keys) —
  ledger the delivery as `sent` immediately (`recordChannelSuccess`).
- Dashboard: zero deliveries → `successRate = null`, rendered as **"—"** with an honest
  "no deliveries in the last 7 days" caption.

## 4. Verification Plan

- [x] Tests: media-less story allowed for photo package; excluded-kind story still
      denied; api channel ledgered as `sent`; dashboard null rate (6 new/updated).
- [x] `php artisan test` — **1006 passed / 0 failed**.
- [ ] Live: dispatch `FanoutStory` for an existing published story → deliveries rows
      appear → distribution log populated → dashboard rate from real data.

## 5. Completion Notes

_(pending)_
