<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('kind', 12);
            $table->text('description')->nullable();
            $table->jsonb('entitlement_filter');
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->string('status', 12)->default('active');
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE packages ADD CONSTRAINT packages_kind_check CHECK (kind IN ('news','photos','bundle'))");
            DB::statement("ALTER TABLE packages ADD CONSTRAINT packages_status_check CHECK (status IN ('active','archived'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
