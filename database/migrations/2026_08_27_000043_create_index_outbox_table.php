<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('index_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('index_name', 20);
            $table->string('op', 8);
            $table->string('document_id', 40);
            $table->string('status', 10)->default('pending');
            $table->smallInteger('attempts')->default(0);
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('processed_at')->nullable();

            $table->index(['status', 'created_at'], 'index_outbox_status_created_index');
            $table->index('document_id', 'index_outbox_document_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE index_outbox ADD CONSTRAINT index_outbox_index_name_check CHECK (index_name IN ('main','archive','media'))");
            DB::statement("ALTER TABLE index_outbox ADD CONSTRAINT index_outbox_op_check CHECK (op IN ('upsert','delete'))");
            DB::statement("ALTER TABLE index_outbox ADD CONSTRAINT index_outbox_status_check CHECK (status IN ('pending','done','failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('index_outbox');
    }
};
