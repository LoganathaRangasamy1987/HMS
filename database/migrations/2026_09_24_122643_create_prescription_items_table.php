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
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medicine_name', 150);
            $table->string('strength', 100);
            $table->string('dose', 100);
            $table->string('frequency', 100);
            $table->string('duration', 100);
            $table->string('route', 50);
            $table->string('timing', 100)->nullable();
            $table->string('advice', 500)->nullable();
            $table->timestamps();
            $table->index(['prescription_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
