# Task: COS-79-002 — Effective package/add-on entitlement + quota resolver

**Status:** Pending
**Dependencies:** COS-79-001
**Parent ADR:** DEC-007; proposed DEC-020

## 1. Contract (What)
- Effective base packages + add-ons union languages/categories/media access.
- Quotas: base-package maximum; null wins as unlimited; explicit client override replaces
  that dimension; no free-text quota parsing.

## 2. Logic (How)
1. Resolve active subscriptions in the start/end window.
2. Separate base packages from `is_addon` packages.
3. Resolve quota union + override presence/values; expose one stable result for consumers.

## 3. Context (Where)
- `EntitlementResolver`, `QuotaService`, `PackageRepository` as needed.
- Tests: union, add-on union, inactive/expired, null vs zero, override inherit/numeric/null.

## 4. Test Criteria
- [ ] Table-driven entitlement/quota matrix passes.
- [ ] Existing calendar-month usage calculation remains unchanged.
- [ ] No per-asset N+1 entitlement queries.
