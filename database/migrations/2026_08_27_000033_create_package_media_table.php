<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_media', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->timestamptz('added_at')->useCurrent();
            $table->primary(['package_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_media');
    }
};
