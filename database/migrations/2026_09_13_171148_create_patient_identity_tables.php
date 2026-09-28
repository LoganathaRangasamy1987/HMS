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
        Schema::create('patient_number_sequences', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['hospital_id', 'year']);
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->comment('Original registration branch; patient identity is shared within the hospital.');
            $table->string('uhid', 64);
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('date_of_birth_unknown')->default(false);
            $table->string('gender', 20);
            $table->string('mobile', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_mobile', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['registered_by', 'hospital_id'])->references(['id', 'hospital_id'])->on('users')->restrictOnDelete();
            $table->unique(['hospital_id', 'uhid']);
            $table->unique(['id', 'hospital_id']);
            $table->index(['hospital_id', 'mobile']);
            $table->index(['hospital_id', 'last_name', 'first_name']);
            $table->index(['hospital_id', 'first_name', 'last_name']);
            $table->index(['hospital_id', 'created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
        Schema::dropIfExists('patient_number_sequences');
    }
};
