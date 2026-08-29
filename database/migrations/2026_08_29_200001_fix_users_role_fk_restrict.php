<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $fk = DB::selectOne("SELECT conname FROM pg_constraint WHERE conrelid='users'::regclass AND contype='f' AND array_position(conkey, (SELECT attnum FROM pg_attribute WHERE attrelid='users'::regclass AND attname='role_id')) IS NOT NULL");
            if ($fk) {
                DB::statement('ALTER TABLE users DROP CONSTRAINT "' . $fk->conname . '"');
            }
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT');
        } else {
            Schema::table('users', function (Blueprint $table) {
                try {
                    $table->dropConstrainedForeignId('role_id');
                } catch (\Throwable $e) {
                }
            });
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete()->change();
            });
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_id_foreign');
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL');
        }
    }
};
