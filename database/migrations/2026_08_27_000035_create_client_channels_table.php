<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type', 12);
            $table->jsonb('config');
            $table->string('status', 12)->default('active');
            $table->timestamptz('last_success_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE client_channels ADD CONSTRAINT client_channels_type_check CHECK (type IN ('api','ftp','webhook'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_channels');
    }
};
