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
        Schema::create('diagnosis_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnosis_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('description', 1000);
            $table->string('code_system', 50)->nullable();
            $table->string('code', 50)->nullable();
            $table->string('reason', 500);
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();
            $table->index(['diagnosis_id', 'corrected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnosis_corrections');
    }
};
