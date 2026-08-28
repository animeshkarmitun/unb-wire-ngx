<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 12);
            $table->bigInteger('actor_id')->nullable();
            $table->string('action', 60);
            $table->string('entity_type', 40)->nullable();
            $table->bigInteger('entity_id')->nullable();
            $table->jsonb('diff')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamptz('created_at')->useCurrent();

            $table->index('created_at', 'audit_logs_created_at_index');
            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_index');
            $table->index(['actor_type', 'actor_id'], 'audit_logs_actor_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_actor_type_check CHECK (actor_type IN ('user','client_user','system','worker'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
