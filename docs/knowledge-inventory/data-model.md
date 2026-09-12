# Data Model — UNB Wire

> **Canonical schema: [`app-data/v1-database-design.md`](../../app-data/v1-database-design.md)**
> (PostgreSQL 16). That document is the full DB contract — every table, column,
> constraint, index, partition strategy, and the ordered migration task list (its §10).
> Do not duplicate it here; this file only records how we apply it.

---

## 1. Conventions in force (from DB design §1)

- **PKs:** `id` bigint identity; anything exposed in URLs/APIs/webhooks also gets a
  `public_id` ULID — sequential ids never leak to clients.
- **Time:** `timestamptz` everywhere, server-set; business-local concepts computed
  app-side in Asia/Dhaka (NFR §11).
- **Enums:** `varchar` + `CHECK` constraint (not native PG enums); Laravel enums
  cast to string on models.
- **JSONB** only where shape genuinely varies (AI packs, derivative manifests, EXIF,
  channel configs, entitlement filters, audit diffs); GIN indexes only on filtered
  JSONB.
- **Search is not in the DB:** content writes commit together with an
  `index_outbox` row (transactional outbox → Meilisearch worker).
- **Soft deletes** only on `users`, `stories`, `media_assets`, `clients`.
- **Append-only** (`story_notes`, `story_events`, `audit_logs`, `deliveries`,
  `downloads`): enforced by DB grants — app role gets INSERT+SELECT only.
- **Optimistic locking:** `stories.version` incremented on every save; stale → 409.
- **Idempotency:** unique `idempotency_key` on worker-written tables (deliveries).
- **Multi-language:** `language` ('en'|'bn') + `mirror_of_id` self-FK — not
  row-per-translation.

## 2. Table groups (details in the canonical doc)

| Domain | Tables |
|---|---|
| Identity & access | `roles`, `role_permissions`, `users`, `devices` (retained, DEC-011) |
| Clients | `clients`, `client_users`, `client_api_keys` |
| Taxonomy | `categories`, `tags`, `story_tag`, `media_tag` |
| Stories | `stories`, `story_versions`, `story_notes` (+`is_internal`), `story_events`, `story_media` |
| Media | `media_batches` (+`assignment_id`), `media_assets`, `media_reviews`, `upload_sessions`, `assignments` (reintroduced, DEC-011) |
| Distribution | `packages`, `package_media`, `client_packages`, `client_channels`, `deliveries` (partitioning deferred to ~5M rows, DEC-011) |
| AI & ops | `ai_generations` (partitioning deferred), `ai_token_usage_daily`, `settings`, `notifications`, `audit_logs` (grant-restricted, pgsql REVOKE), `downloads`, `index_outbox` |
| Billing | `invoices`, `invoice_lines` (ratified M5, DEC-011) |

## 3. Build order

Follow the migration task breakdown in the canonical doc **§10**, with DEC-011 adjustments: `assignments` re-added (§6 parity), `devices` retained, `invoices`/`is_internal` ratified. Partitioning remains deferred per §9.

Acceptance per migration task (from the canonical doc): FK + CHECK constraints
exactly as specced; all indexes present; `timestamptz` everywhere (grep generated
SQL for bare `timestamp` → must be 0); append-only tables carry the grant-revoke
statement in the same migration.

### `story_events` action vocabulary (M10-HIST)

Spec-aligned verb actions: `created`, `sent_to_review`, `changes_requested`, `approved`,
`published`, `auto_published`, `killed`, `archived`, `handover`, `ai_applied`, `note_added`,
`restored`. Payload jsonb carries context per action (gate, from_user/to_user, reason,
fields, note_id, from_version/to_version). Defined in `StoryEvent::ACTIONS` const.

### `role_permissions` module additions (DEC-012, M10-HIST)

Two view-only modules added to the seed matrix:
- `history` — story timeline + version history visibility. Admin/Editor/Strategist/Admin Report/Uploaders: view=1. Business/Client: view=0.
- `audit` — audit log browser. Admin + Admin Report: view=1. All others: view=0.

## 4. Lifecycle (hot → warm → cold)

Per canonical doc §9: `stories` stay one table in v1 (archive sweep flips status +
moves search doc main→archive); `deliveries`/`audit_logs`/`downloads`/
`ai_generations` are monthly RANGE partitions detached to an archive schema after
12 months (audit kept 7 years); media originals tier to S3 IA/Glacier, derivatives
stay hot; `index_outbox` rows purged 7 days after `done`.
