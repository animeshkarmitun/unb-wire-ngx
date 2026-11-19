# Task: COS-79-005 — Quota UI parity, migration rollout, and live matrix

**Status:** Pending
**Dependencies:** COS-79-001 through COS-79-004
**Parent ADR:** proposed DEC-020

## 1. Contract (What)
- Admin Clients usage, portal context, and Add News “Who gets what” derive from the
  effective resolver — no hardcoded quota fallback/free-text parsing.
- Tier/add-on wording agrees with delivery and portal access.

## 2. Logic (How)
1. Replace hardcoded values in Clients list/drawer and Add News step 3.
2. Keep portal context quota output stable; render `null` and `0` distinctly.
3. Run data backfill, cache/index refresh, and smoke each tier/add-on.

## 3. Context (Where)
- `ClientService`, Clients Blade, Add News step 3, portal quota displays, seeders/tests,
  knowledge inventory.

## 4. Test Criteria
- [ ] Premium/Standard/Basic + add-on + override display matrix matches API values.
- [ ] Backfill reconciles legacy `notes.tier_quotas` with package defaults.
- [ ] Live Basic/Standard/Premium smoke; no unauthorized asset leaks.
