# Task: COS-79-001 — Numeric package quotas + client quota overrides

**Status:** Pending
**Dependencies:** COS-79 contract in `docs/plans/cos79-package-quota-addon-entitlements.md`
**Parent ADR:** DEC-007; proposed DEC-020

## 1. Contract (What)
- `packages` owns nullable integer `stories_quota` / `media_quota` defaults.
- One `client_entitlement_overrides` row per client; independent override-presence flags
  distinguish inherit, numeric override, and explicit unlimited (`null`).
- Existing `notes.tier_quotas` values migrate without loss.

## 2. Logic (How)
1. Add reversible migrations and model fillable/casts.
2. Backfill existing package defaults by code and client notes overrides.
3. Update seeders/factories to machine-readable quota columns.

## 3. Context (Where)
- Packages/client override migrations, `Package`, new override model, `PackageSeeder`,
  canonical DB design, knowledge data model.
- Tests: migration/backfill and null/zero round trips.

## 4. Test Criteria
- [ ] Fresh migrate/seed + rollback succeed.
- [ ] Backfill preserves `0` distinctly from null/inherit.
- [ ] Schema parity passes.
