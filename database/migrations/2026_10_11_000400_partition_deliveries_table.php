<?php

use App\Support\DeliveryPartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DEC-018 (DEC-011 revisit): deliveries crosses ~5M rows in ~55 days at NFR volume.
 * Convert to the monthly RANGE partitions specced in v1-database-design.md §7:
 * - PK becomes (id, created_at) — PG requires the partition key in the PK.
 * - Global UNIQUE(idempotency_key) is impossible on a partitioned table; replaced by
 *   UNIQUE(idempotency_key, created_at) + the existing app-level check-then-insert
 *   in FanoutStory (idempotency keys are sha256(story-channel-version), so realistic
 *   duplicate races are same-month only).
 * - Partitions created ahead by partitions:ensure (command); default partition is the
 *   safety net; 12-month-old partitions detach to the archive schema (sweep command).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE deliveries RENAME TO deliveries_legacy');

        // Index names are schema-wide — the legacy table keeps them after the rename,
        // so free them before recreating same-named indexes on the partitioned parent.
        foreach ([
            'deliveries_deliverable_index',
            'deliveries_client_created_index',
            'deliveries_failed_created_index',
        ] as $legacyIndex) {
            DB::statement("DROP INDEX IF EXISTS {$legacyIndex}");
        }

        DB::statement(<<<'SQL'
            CREATE TABLE deliveries (
                id bigserial NOT NULL,
                deliverable_type varchar(8) NOT NULL,
                deliverable_id bigint NOT NULL,
                client_id bigint NOT NULL,
                channel_id bigint NOT NULL,
                status varchar(12) NOT NULL DEFAULT 'queued',
                attempt_count integer NOT NULL DEFAULT 0,
                idempotency_key varchar(80) NOT NULL,
                payload_hash char(64) NOT NULL,
                response_code integer,
                error text,
                sent_at timestamptz,
                delivered_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (id, created_at),
                CONSTRAINT deliveries_deliverable_type_check CHECK (deliverable_type IN ('story','media')),
                CONSTRAINT deliveries_status_check CHECK (status IN ('queued','sent','delivered','failed','skipped_entitlement'))
            ) PARTITION BY RANGE (created_at)
        SQL);

        DB::statement('ALTER TABLE deliveries ADD CONSTRAINT deliveries_client_id_foreign FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE deliveries ADD CONSTRAINT deliveries_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES client_channels (id) ON DELETE RESTRICT');

        DB::statement('CREATE INDEX deliveries_client_created_index ON deliveries (client_id, created_at)');
        DB::statement('CREATE INDEX deliveries_deliverable_index ON deliveries (deliverable_type, deliverable_id)');
        DB::statement("CREATE INDEX deliveries_failed_created_index ON deliveries (status, created_at) WHERE status = 'failed'");
        DB::statement('CREATE UNIQUE INDEX deliveries_idempotency_created_index ON deliveries (idempotency_key, created_at)');

        try {
            DB::statement('REVOKE UPDATE, DELETE ON deliveries FROM PUBLIC');
        } catch (Throwable) {
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $next = $now->add(new DateInterval('P1M'));
        foreach ([$now, $next] as $month) {
            [$from, $to] = DeliveryPartitions::bounds($month);
            $ym = $month->format('Ym');
            DB::statement("CREATE TABLE deliveries_{$ym} PARTITION OF deliveries FOR VALUES FROM ('{$from}') TO ('{$to}')");
        }
        DB::statement('CREATE TABLE deliveries_default PARTITION OF deliveries DEFAULT');

        DB::statement(<<<'SQL'
            INSERT INTO deliveries (id, deliverable_type, deliverable_id, client_id, channel_id, status, attempt_count, idempotency_key, payload_hash, response_code, error, sent_at, delivered_at, created_at)
            SELECT id, deliverable_type, deliverable_id, client_id, channel_id, status, attempt_count, idempotency_key, payload_hash, response_code, error, sent_at, delivered_at, created_at
            FROM deliveries_legacy
        SQL);

        DB::statement("SELECT setval(pg_get_serial_sequence('deliveries', 'id'), COALESCE((SELECT max(id) FROM deliveries), 0) + 1, false)");

        DB::statement('DROP TABLE deliveries_legacy');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE deliveries_flat (
                id bigserial NOT NULL PRIMARY KEY,
                deliverable_type varchar(8) NOT NULL,
                deliverable_id bigint NOT NULL,
                client_id bigint NOT NULL,
                channel_id bigint NOT NULL,
                status varchar(12) NOT NULL DEFAULT 'queued',
                attempt_count integer NOT NULL DEFAULT 0,
                idempotency_key varchar(80) NOT NULL,
                payload_hash char(64) NOT NULL,
                response_code integer,
                error text,
                sent_at timestamptz,
                delivered_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT deliveries_deliverable_type_check CHECK (deliverable_type IN ('story','media')),
                CONSTRAINT deliveries_status_check CHECK (status IN ('queued','sent','delivered','failed','skipped_entitlement'))
            )
        SQL);
        DB::statement('ALTER TABLE deliveries_flat ADD CONSTRAINT deliveries_client_id_foreign FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE deliveries_flat ADD CONSTRAINT deliveries_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES client_channels (id) ON DELETE RESTRICT');
        DB::statement('CREATE UNIQUE INDEX deliveries_idempotency_key_unique ON deliveries_flat (idempotency_key)');
        DB::statement('CREATE INDEX deliveries_client_created_index ON deliveries_flat (client_id, created_at)');
        DB::statement('CREATE INDEX deliveries_deliverable_index ON deliveries_flat (deliverable_type, deliverable_id)');
        DB::statement("CREATE INDEX deliveries_failed_created_index ON deliveries_flat (status, created_at) WHERE status = 'failed'");

        DB::statement(<<<'SQL'
            INSERT INTO deliveries_flat (id, deliverable_type, deliverable_id, client_id, channel_id, status, attempt_count, idempotency_key, payload_hash, response_code, error, sent_at, delivered_at, created_at)
            SELECT id, deliverable_type, deliverable_id, client_id, channel_id, status, attempt_count, idempotency_key, payload_hash, response_code, error, sent_at, delivered_at, created_at
            FROM deliveries
        SQL);

        DB::statement("SELECT setval(pg_get_serial_sequence('deliveries_flat', 'id'), COALESCE((SELECT max(id) FROM deliveries_flat), 0) + 1, false)");

        DB::statement('DROP TABLE deliveries CASCADE');
        DB::statement('ALTER TABLE deliveries_flat RENAME TO deliveries');

        try {
            DB::statement('REVOKE UPDATE, DELETE ON deliveries FROM PUBLIC');
        } catch (Throwable) {
        }
    }
};
