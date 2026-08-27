<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            $table->timestamptz('starts_at');
            $table->timestamptz('ends_at')->nullable();
            $table->string('status', 12)->default('active');
            $table->timestamptz('created_at')->useCurrent();
            $table->unique(['client_id', 'package_id', 'starts_at'], 'client_packages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_packages');
    }
};
