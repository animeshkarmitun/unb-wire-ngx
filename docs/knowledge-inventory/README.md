# Knowledge Inventory — UNB Wire

> Deep-dive technical and domain documentation. All agents should review these before modifying core business logic, schema, or system architecture.

---

## Index of Documents

| Document | Description |
|----------|-------------|
| [`domain.md`](domain.md) | Business glossary, editorial lifecycle, subscriber tiers, wire feed rules |
| [`decisions.md`](decisions.md) | Architecture Decision Log (ADRs: DEC-001, etc.) |
| [`architecture.md`](architecture.md) | Tech stack, routing patterns, caching, queues, and webhook delivery |
| [`data-model.md`](data-model.md) | Entity relationships, audit columns, indexing, soft deletes |

---

## Maintenance & Update Rule

Whenever changes affect business rules, architecture, API shapes, or schema, update the corresponding document in the same commit/PR as the code.
