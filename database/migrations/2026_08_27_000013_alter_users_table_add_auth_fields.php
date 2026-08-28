<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->after('id');
            $table->foreignId('role_id')->nullable()->after('public_id')->constrained('roles')->nullOnDelete();
            $table->string('desk', 40)->nullable()->after('role_id');
            $table->string('timezone', 64)->default('Asia/Dhaka')->after('desk');
            $table->string('status', 16)->default('active')->after('timezone');
            $table->timestamptz('last_seen_at')->nullable()->after('status');
            $table->timestamptz('deleted_at')->nullable()->after('updated_at');
        });

        DB::table('users')->whereNull('public_id')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['public_id' => (string) Str::ulid()]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable(false)->unique()->change();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext USING email::citext');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','invited','deactivated'))");
            DB::statement('ALTER TABLE users ALTER COLUMN created_at TYPE timestamptz USING created_at::timestamptz');
            DB::statement('ALTER TABLE users ALTER COLUMN updated_at TYPE timestamptz USING updated_at::timestamptz');
            DB::statement('ALTER TABLE users ALTER COLUMN email_verified_at TYPE timestamptz USING email_verified_at::timestamptz');
            DB::statement('ALTER TABLE users ALTER COLUMN last_seen_at TYPE timestamptz USING last_seen_at::timestamptz');
            DB::statement('ALTER TABLE users ALTER COLUMN deleted_at TYPE timestamptz USING deleted_at::timestamptz');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['public_id', 'desk', 'timezone', 'status', 'last_seen_at', 'deleted_at']);
        });
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE varchar(255) USING email::varchar');
    }
};
