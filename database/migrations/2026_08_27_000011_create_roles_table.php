<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('type', 16);
            $table->text('description')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_type_check CHECK (type IN ('system','custom','client'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
