<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('date_format', 16)->default('dmy')->after('timezone');
            $table->string('density', 16)->default('comfortable')->after('date_format');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_date_format_check CHECK (date_format IN ('dmy','mdy','iso'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_density_check CHECK (density IN ('comfortable','compact'))");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_date_format_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_density_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['date_format', 'density']);
        });
    }
};
