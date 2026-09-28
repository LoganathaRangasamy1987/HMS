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
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('registration_number', 100);
            $table->string('qualification', 500);
            $table->string('specialization', 200);
            $table->decimal('consultation_fee', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['user_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('users')->restrictOnDelete();
            $table->unique(['hospital_id', 'branch_id', 'user_id']);
            $table->unique(['hospital_id', 'registration_number']);
            $table->index(['hospital_id', 'branch_id', 'department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
