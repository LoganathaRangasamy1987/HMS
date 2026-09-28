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
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('description', 1000);
            $table->string('code_system', 50)->nullable();
            $table->string('code', 50)->nullable();
            $table->timestamp('diagnosed_at');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['encounter_id', 'type', 'diagnosed_at']);
            $table->index(['hospital_id', 'patient_id', 'diagnosed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
