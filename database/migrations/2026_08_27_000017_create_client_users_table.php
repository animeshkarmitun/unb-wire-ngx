<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('password');
            $table->foreignId('client_role_id')->constrained('roles')->restrictOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamptz('last_login_at')->nullable();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE client_users ADD COLUMN email citext NOT NULL');
            DB::statement('CREATE UNIQUE INDEX client_users_email_unique ON client_users (email)');
            DB::statement("ALTER TABLE client_users ADD CONSTRAINT client_users_status_check CHECK (status IN ('active','invited','deactivated'))");
        } else {
            Schema::table('client_users', function (Blueprint $table) {
                $table->string('email')->unique()->after('name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_users');
    }
};
