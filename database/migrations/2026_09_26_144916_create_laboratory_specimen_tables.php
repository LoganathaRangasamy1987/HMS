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
        Schema::create('lab_specimen_number_sequences', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['hospital_id', 'year']);
        });
        Schema::create('lab_specimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_order_item_id')->constrained()->restrictOnDelete();
            $table->string('identifier', 64);
            $table->unsignedSmallInteger('attempt');
            $table->string('status', 30);
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('collected_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('processing_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('processing_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'identifier']);
            $table->unique(['lab_order_item_id', 'attempt']);
            $table->index(['branch_id', 'status', 'collected_at']);
            $table->index(['lab_order_id', 'status']);
        });
        Schema::create('lab_specimen_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_specimen_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['lab_specimen_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_specimen_events');
        Schema::dropIfExists('lab_specimens');
        Schema::dropIfExists('lab_specimen_number_sequences');
    }
};
