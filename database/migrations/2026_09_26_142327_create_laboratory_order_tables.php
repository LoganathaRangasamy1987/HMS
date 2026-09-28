<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_order_number_sequences', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['hospital_id', 'year']);
        });
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('doctor_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 64);
            $table->string('status', 20)->default('ORDERED');
            $table->text('clinical_notes')->nullable();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('ordered_at');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'number']);
            $table->index(['branch_id', 'status', 'ordered_at']);
            $table->index(['hospital_id', 'patient_id', 'ordered_at']);
        });
        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_test_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_test_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('test_code', 40);
            $table->string('test_name', 150);
            $table->unsignedInteger('version');
            $table->string('currency', 3)->default('INR');
            $table->decimal('price', 12, 2);
            $table->string('status', 20)->default('ORDERED');
            $table->timestamps();
            $table->unique(['lab_order_id', 'lab_test_id']);
            $table->index(['lab_order_id', 'status']);
        });
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('service_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $invoiceIds = DB::table('lab_orders')->pluck('invoice_id');
        $invoiceLineIds = DB::table('lab_order_items')->whereNotNull('invoice_line_id')->pluck('invoice_line_id');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_order_number_sequences');
        DB::table('invoice_lines')->whereIn('id', $invoiceLineIds)->delete();
        DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('service_item_id')->nullable(false)->change();
        });
    }
};
