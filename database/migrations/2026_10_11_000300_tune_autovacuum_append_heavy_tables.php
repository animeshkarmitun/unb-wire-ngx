<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'deliveries',
        'downloads',
        'notifications',
        'audit_logs',
        'story_events',
        'index_outbox',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} SET (autovacuum_vacuum_scale_factor = 0.02, autovacuum_analyze_scale_factor = 0.01)");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} SET (autovacuum_vacuum_scale_factor = 0.2, autovacuum_analyze_scale_factor = 0.1)");
        }
    }
};
