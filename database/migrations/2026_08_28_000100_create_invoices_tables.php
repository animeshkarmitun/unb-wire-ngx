<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('status', 12)->default('draft');
            $table->timestamptz('issued_at')->nullable();
            $table->timestamptz('due_at')->nullable();
            $table->timestamptz('paid_at')->nullable();
            $table->timestamptz('created_at')->useCurrent();
            $table->timestamptz('updated_at')->useCurrent();
            $table->unique(['client_id', 'period_start'], 'invoices_client_period_unique');
        });
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('description', 200);
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->timestamptz('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
