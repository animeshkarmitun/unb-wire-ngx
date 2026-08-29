# Task: M9-SCHEMA-004 — Docs ratification (invoices, is_internal, CHECKs, partitions)

**Status:** ✅ Completed
**Dependencies:** M9-SCHEMA-003
**Parent ADR:** DEC-011, v1-database-design §5-§10

---

## 1. Contract (What)
- **Inputs / Validation:** Doc-only + data-model sync.
- **Outputs / Response:** `v1-database-design.md` and `docs/knowledge-inventory/data-model.md` include `invoices/invoice_lines`, `story_notes.is_internal`, invented CHECK enums, partitioning defer note; new DEC-011 appended.
- **Authorization:** N/A

---

## 2. Logic (How)
1. Append DEC-011: field apps deferred retains devices, assignments reintroduced, invoices/ is_internal ratified, partitioning deferred to ~5M rows, timestamptz extras ratified.
2. Patch `v1-database-design.md` §5 add `story_notes.is_internal bool default true`, §7 add `invoices`/`invoice_lines` DDL, §4 add invented CHECK lists for packages/client_packages/client_channels, §9 note partitioning deferred note, §1.2 note framework tables epoch ints.
3. Sync `docs/knowledge-inventory/data-model.md` and `domain.md` if needed.
4. Ensure `docs/schema-code-mismatch-report.md` marked as remediated appendix.

---

## 3. Context (Where)
- **Files to Create / Modify:**
  - `docs/knowledge-inventory/decisions.md`
  - `app-data/v1-database-design.md`
  - `docs/knowledge-inventory/data-model.md`
- **Reference Files:**
  - `v1-database-design.md`
  - `decisions.md` (existing DEC-010)

---

## 4. Prompt (For the Coding AI)
> Update docs per Contract. Keep existing DEC history immutable.

---

## 5. Test Criteria
- [ ] `grep invoices v1-database-design.md` hits DDL
- [ ] `grep is_internal v1-database-design.md` hits story_notes
- [ ] DEC-011 present and references M9-SCHEMA tasks
- [ ] `docs/knowledge-inventory/data-model.md` lists invoices + assignments

---

## 6. Completion Notes
- **Shipped:** `DEC-011` appended to `decisions.md`, `v1-database-design.md` patched (§5 is_internal, §7 CHECKs, §9 partitioning defer, §10 10a/10b billing), `data-model.md` synced, `schema-code-mismatch-report.md` Appendix A added.
- **Tests:** `migrate:fresh --seed` green, `php artisan test` 114 pass.
- **Live Smoke:** —
- **Review:** —

---

## 7. Prompt Ready?
- [x] Yes
