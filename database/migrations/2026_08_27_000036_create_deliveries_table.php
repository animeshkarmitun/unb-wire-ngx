<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('deliverable_type', 8);
            $table->bigInteger('deliverable_id');
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('channel_id')->constrained('client_channels')->restrictOnDelete();
            $table->string('status', 12);
            $table->integer('attempt_count')->default(0);
            $table->string('idempotency_key', 80)->unique();
            $table->char('payload_hash', 64);
            $table->integer('response_code')->nullable();
            $table->text('error')->nullable();
            $table->timestamptz('sent_at')->nullable();
            $table->timestamptz('delivered_at')->nullable();
            $table->timestamptz('created_at')->useCurrent();

            $table->index(['client_id', 'created_at'], 'deliveries_client_created_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_deliverable_type_check CHECK (deliverable_type IN ('story','media'))");
            DB::statement("ALTER TABLE deliveries ADD CONSTRAINT deliveries_status_check CHECK (status IN ('queued','sent','delivered','failed','skipped_entitlement'))");
            DB::statement("CREATE INDEX deliveries_failed_created_index ON deliveries (status, created_at) WHERE status='failed'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
