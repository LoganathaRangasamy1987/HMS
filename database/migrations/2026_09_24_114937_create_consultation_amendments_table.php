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
        Schema::create('consultation_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->restrictOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('examination')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('reason', 500);
            $table->foreignId('amended_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('amended_at');
            $table->timestamps();
            $table->index(['consultation_id', 'amended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_amendments');
    }
};
