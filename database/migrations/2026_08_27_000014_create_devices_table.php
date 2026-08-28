<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('platform', 16);
            $table->string('app_version', 20)->nullable();
            $table->timestamptz('last_seen_at')->nullable();
            $table->timestamptz('revoked_at')->nullable();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE devices ADD CONSTRAINT devices_platform_check CHECK (platform IN ('ios','android','web'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
