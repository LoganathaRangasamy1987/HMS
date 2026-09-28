<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('type', 20);
            $table->string('description', 1000)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('base_price', 12, 2);
            $table->decimal('tax_rate_percent', 5, 2)->default(0);
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'type', 'status']);
        });
        Schema::create('service_branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('base_price', 12, 2)->nullable();
            $table->decimal('tax_rate_percent', 5, 2)->nullable();
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['service_item_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_branch_prices');
        Schema::dropIfExists('service_items');
    }
};
