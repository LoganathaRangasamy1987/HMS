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
        Schema::create('pharmacy_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_sale_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_purchase_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 14, 3);
            $table->string('reason', 500);
            $table->string('status', 20)->default('PENDING');
            $table->uuid('request_key');
            $table->char('payload_hash', 64);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'request_key']);
            $table->index(['branch_id', 'status', 'requested_at']);
            $table->index(['medicine_batch_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_stock_adjustments');
    }
};
