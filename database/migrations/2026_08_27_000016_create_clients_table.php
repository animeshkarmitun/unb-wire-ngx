<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 160);
            $table->string('code', 32)->unique();
            $table->string('type', 24);
            $table->char('country', 2)->default('BD');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
            $table->timestamptz('deleted_at')->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clients ADD COLUMN billing_email citext NULL');
            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_type_check CHECK (type IN ('newspaper','tv','online','radio','govt','agency'))");
            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status IN ('active','suspended','closed'))");
        } else {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('billing_email')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
