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
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('allergen', 150);
            $table->string('reaction', 500)->nullable();
            $table->string('severity', 20)->default('unknown');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign(['patient_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('patients')->restrictOnDelete();
            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['recorded_by', 'hospital_id'])->references(['id', 'hospital_id'])->on('users')->restrictOnDelete();
            $table->index(['hospital_id', 'patient_id', 'status']);
        });
        Schema::create('patient_medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('condition', 200);
            $table->date('onset_date')->nullable();
            $table->boolean('onset_date_unknown')->default(false);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign(['patient_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('patients')->restrictOnDelete();
            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['recorded_by', 'hospital_id'])->references(['id', 'hospital_id'])->on('users')->restrictOnDelete();
            $table->index(['hospital_id', 'patient_id', 'status']);
        });
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 30);
            $table->string('name');
            $table->string('path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->foreign(['patient_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('patients')->restrictOnDelete();
            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['uploaded_by', 'hospital_id'])->references(['id', 'hospital_id'])->on('users')->restrictOnDelete();
            $table->index(['hospital_id', 'patient_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
        Schema::dropIfExists('patient_medical_histories');
        Schema::dropIfExists('patient_allergies');
    }
};
