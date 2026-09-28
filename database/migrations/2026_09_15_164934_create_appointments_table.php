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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_profile_id')->constrained()->restrictOnDelete();
            $table->date('appointment_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('token_number');
            $table->string('type', 20);
            $table->string('status', 20)->default('BOOKED');
            $table->string('reason', 500)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->uuid('request_key')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'doctor_profile_id', 'appointment_date', 'token_number'], 'appointment_token_unique');
            $table->unique(['hospital_id', 'request_key']);
            $table->index(['doctor_profile_id', 'appointment_date', 'starts_at', 'status'], 'appointment_slot_index');
            $table->index(['branch_id', 'appointment_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
