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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('generic_name', 150)->nullable();
            $table->string('form', 50);
            $table->string('strength', 100);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
