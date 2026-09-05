<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        foreach (['story_notes','story_events','deliveries','audit_logs','downloads'] as $t) {
            try { DB::statement("REVOKE UPDATE, DELETE ON {$t} FROM PUBLIC"); } catch (\Throwable $e) {}
        }
        try { DB::statement("REVOKE UPDATE, DELETE ON audit_logs FROM PUBLIC"); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        foreach (['story_notes','story_events','deliveries','audit_logs','downloads'] as $t) {
            try { DB::statement("GRANT UPDATE, DELETE ON {$t} TO PUBLIC"); } catch (\Throwable $e) {}
        }
    }
};
