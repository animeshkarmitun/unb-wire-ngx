# Task: COS-72 — AP Photo Manager honest sync state

**Status:** 🔧 In Progress
**Priority:** P3
**Linear:** [COS-72](https://linear.app/cosmosgroupcom/issue/COS-72/ap-ap-photo-manager-shows-a-future-last-synced)
**Affected surfaces:** ApPhotoManager + blade sync note
**Parent contracts:** COS-30 (honest states — no fake success surfaces)

---

## 1. Issue Summary

"Last synced 9:14 PM" was a **hardcoded default** rendered in the future; the sync line
promised "auto-syncs every 15 minutes" although no AP partner integration exists
(M18-AP is backlog).

## 2. Fix

- `lastSyncTime` derives from the newest AP-source asset (Dhaka time) or `never`;
  `todaySyncCount` = AP assets created today.
- `syncNow` no longer fakes a timestamp (stays honest — feed not configured).
- Sync note copy: "AP wire sync · Last synced …" or
  "AP wire sync not configured yet · N photos in library".

## 3. Verification

- [x] Tests updated: real derived count, no hardcoded time.
- [x] `php artisan test` — **1029 passed / 0 failed**; pint clean.
- [x] **Live:** future time gone — the sync line now reads
      "AP wire sync · Last synced 12:00 AM · 0 new photos today" (real seed-data asset
      time; the not-configured branch shows when no AP assets exist).

## 4. Answer to the QA question

**Is AP sync connected on the test server, or seed data?** Neither — AP wire integration
is **not implemented yet** (tracked as `M18-AP` backlog). The photos on the server are
seed data. There is no auto-sync to be stale; the page now says so honestly. When the
partner integration lands (M18-AP-001), this panel is the natural home for real sync logs.

## 5. Completion Notes

- PR #82 merged + deployed.
