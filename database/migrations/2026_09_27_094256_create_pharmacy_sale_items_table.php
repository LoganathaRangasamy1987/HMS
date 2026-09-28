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
        Schema::create('pharmacy_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_line_id')->constrained()->restrictOnDelete();
            $table->string('medicine_code', 40);
            $table->string('medicine_name', 150);
            $table->string('batch_number', 100);
            $table->date('expires_on');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_rate_percent', 5, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();
            $table->unique('prescription_item_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('pharmacy_sale_item_id')->nullable()->after('pharmacy_purchase_item_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pharmacy_sale_item_id');
        });
        Schema::dropIfExists('pharmacy_sale_items');
    }
};
