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
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->unique()->constrained()->restrictOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('examination')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
