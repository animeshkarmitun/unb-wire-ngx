<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DEC-019 (M15-SCALE-004): the staff Meilisearch index writes through index_outbox —
 * extend the index_name CHECK enum with 'staff'. Live smoke caught the violation on
 * pgsql (sqlite tests don't enforce the pgsql-only CHECK).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE index_outbox DROP CONSTRAINT IF EXISTS index_outbox_index_name_check');
        DB::statement("ALTER TABLE index_outbox ADD CONSTRAINT index_outbox_index_name_check CHECK (index_name IN ('main','archive','media','staff'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE index_outbox DROP CONSTRAINT IF EXISTS index_outbox_index_name_check');
        DB::statement("ALTER TABLE index_outbox ADD CONSTRAINT index_outbox_index_name_check CHECK (index_name IN ('main','archive','media'))");
    }
};
