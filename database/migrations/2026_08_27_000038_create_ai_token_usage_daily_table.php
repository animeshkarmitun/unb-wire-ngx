<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_usage_daily', function (Blueprint $table) {
            $table->date('date');
            $table->string('scope', 24);
            $table->string('kind', 12);
            $table->bigInteger('tokens')->default(0);
            $table->bigInteger('cost_micros')->default(0);

            $table->primary(['date', 'scope', 'kind'], 'ai_token_usage_daily_pkey');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_usage_daily');
    }
};
