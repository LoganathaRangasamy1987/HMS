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
        Schema::create('pharmacy_purchase_number_sequences', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['hospital_id', 'year']);
        });
        Schema::create('pharmacy_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_supplier_id')->constrained()->restrictOnDelete();
            $table->string('number', 60);
            $table->string('supplier_invoice_number', 100);
            $table->date('purchase_date');
            $table->string('status', 20)->default('RECEIVED');
            $table->char('currency', 3)->default('INR');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('total', 14, 2);
            $table->uuid('request_key');
            $table->char('payload_hash', 64);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['hospital_id', 'number']);
            $table->unique(['hospital_id', 'request_key']);
            $table->unique(['hospital_id', 'pharmacy_supplier_id', 'supplier_invoice_number']);
            $table->index(['branch_id', 'purchase_date']);
        });
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100);
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on');
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('on_hand_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('sale_price', 14, 2);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['branch_id', 'medicine_id', 'batch_number']);
            $table->index(['branch_id', 'expires_on', 'status']);
        });
        Schema::create('pharmacy_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->string('medicine_code', 40);
            $table->string('medicine_name', 150);
            $table->string('batch_number', 100);
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on');
            $table->string('unit_symbol', 30)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('free_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('sale_price', 14, 2);
            $table->decimal('tax_rate_percent', 5, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_purchase_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['branch_id', 'medicine_id', 'recorded_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('pharmacy_purchase_items');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('pharmacy_purchases');
        Schema::dropIfExists('pharmacy_purchase_number_sequences');
    }
};
