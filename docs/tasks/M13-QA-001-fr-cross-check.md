# Task: M13-QA-001 — Functional requirements cross-check (spec vs implementation)

**Status:** ✅ Completed
**Dependencies:** M13-PERF-001 (codebase state as of 2026-09-23)
**Parent ADR:** `app-data/v1-functional-requirements.md` (canonical contract), DEC-007/008/011

---

## 1. Contract (What)
- **Inputs:** `app-data/v1-functional-requirements.md` (84 FR items) vs codebase + `tests/Feature`.
- **Outputs:** `docs/fr-cross-check-report.md` — per-FR table (status/evidence/tests/deviations) for FR-NWS, FR-AI, FR-MED, FR-FLD, FR-DST, FR-CLT, FR-PRT, FR-ACC, FR-NTF; undocumented-deviation list; prioritized P0–P2 gap backlog with recommended immediate fixes.
- **Status rubric:** done = implemented + tested + matches spec · partial · missing · changed.

---

## 2. Logic (How)
1. FR inventory extracted from spec headings (84 items; FR-FLD deferred per DEC-008; no FR-SEC items exist in spec — security is NFR §8 → COS-27).
2. Per-family audit of implementation + tests (4 parallel codebase audits + direct verification of FR-NWS).
3. Cross-referenced undocumented deviations against AGENTS.md quality gates (fake-success, schema parity).
4. Findings compiled into prioritized backlog (P0 blockers / P1 broken promises / P2 completeness).

---

## 3. Context (Where)
- **Files Created:** `docs/fr-cross-check-report.md`
- **Reference Files:** `app-data/v1-functional-requirements.md`, `docs/knowledge-inventory/decisions.md`, `docs/tasks/README.md`

---

## 4. Test Criteria
- [x] All 84 FR items have a status row (FLD marked deferred with rationale)
- [x] Every `missing`/`changed` item has a concrete evidence pointer
- [x] Undocumented deviations flagged (7 found)
- [x] Critical gaps prioritized P0–P2 with recommended first fixes

---

## 5. Completion Notes
- **Shipped:** `docs/fr-cross-check-report.md` — matrix totals: **25 done / 35 partial / 6 missing / 9 changed** (75 in-scope FRs; 10 FLD deferred). P0 findings: kill/correction fan-out non-functional (FR-DST-008), media ingest pipeline facade (TUS discards bytes), media embargo unenforced at delivery edges, staff deactivation leaves sessions/devices alive, suspended clients still receive deliveries. No code changed (audit-only).
- **Tests:** N/A (docs-only PR) — suite green pre-PR (730 passed).
- **Live Smoke:** N/A (docs-only).
- **Review:** PR.
