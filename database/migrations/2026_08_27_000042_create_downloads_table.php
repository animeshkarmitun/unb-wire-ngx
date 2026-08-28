<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('client_users')->nullOnDelete();
            $table->string('item_type', 8);
            $table->bigInteger('item_id');
            $table->string('format', 12)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamptz('created_at')->useCurrent();

            $table->index(['client_id', 'created_at'], 'downloads_client_created_index');
            $table->index('created_at', 'downloads_created_at_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE downloads ADD CONSTRAINT downloads_item_type_check CHECK (item_type IN ('story','media'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
