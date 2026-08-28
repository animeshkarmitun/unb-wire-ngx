<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('key_hash', 128)->unique();
            $table->jsonb('scopes')->default('[]');
            $table->integer('rate_limit_rpm')->default(60);
            $table->timestamptz('last_used_at')->nullable();
            $table->timestamptz('expires_at')->nullable();
            $table->timestamptz('revoked_at')->nullable();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_api_keys');
    }
};
