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
        Schema::create('vital_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->string('temperature_unit', 10)->default('°C');
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->string('pulse_unit', 10)->default('bpm');
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->string('respiratory_rate_unit', 10)->default('/min');
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->string('blood_pressure_unit', 10)->default('mmHg');
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->string('oxygen_saturation_unit', 10)->default('%');
            $table->decimal('weight', 7, 2)->nullable();
            $table->string('weight_unit', 10)->default('kg');
            $table->decimal('height', 6, 2)->nullable();
            $table->string('height_unit', 10)->default('cm');
            $table->string('notes', 1000)->nullable();
            $table->timestamp('measured_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['hospital_id', 'patient_id', 'measured_at']);
            $table->index(['encounter_id', 'measured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_observations');
    }
};
