<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 8);
            $table->string('filename', 240);
            $table->bigInteger('size_bytes');
            $table->bigInteger('offset_bytes')->default(0);
            $table->string('status', 12)->default('active');
            $table->jsonb('meta')->nullable();
            $table->timestamptz('expires_at');
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE upload_sessions ADD CONSTRAINT upload_sessions_kind_check CHECK (kind IN ('photo','video'))");
            DB::statement("ALTER TABLE upload_sessions ADD CONSTRAINT upload_sessions_status_check CHECK (status IN ('active','completed','aborted','expired'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
