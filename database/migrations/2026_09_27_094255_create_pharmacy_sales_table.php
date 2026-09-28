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
        Schema::create('pharmacy_sale_number_sequences', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['hospital_id', 'year']);
        });

        Schema::create('pharmacy_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 60);
            $table->string('status', 20)->default('DISPENSED');
            $table->char('currency', 3)->default('INR');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('total', 14, 2);
            $table->uuid('request_key');
            $table->char('payload_hash', 64);
            $table->foreignId('dispensed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('dispensed_at');
            $table->timestamps();
            $table->unique(['hospital_id', 'number']);
            $table->unique(['hospital_id', 'request_key']);
            $table->index(['branch_id', 'dispensed_at']);
            $table->index(['hospital_id', 'patient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_sales');
        Schema::dropIfExists('pharmacy_sale_number_sequences');
    }
};
