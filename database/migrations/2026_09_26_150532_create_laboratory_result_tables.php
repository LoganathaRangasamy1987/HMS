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
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_specimen_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('supersedes_lab_result_id')->nullable()->constrained('lab_results')->restrictOnDelete();
            $table->string('correction_reason', 500)->nullable();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('entered_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['lab_specimen_id', 'revision']);
            $table->index(['lab_order_id', 'status']);
            $table->index(['branch_id', 'status', 'entered_at']);
        });
        Schema::create('lab_result_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_result_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_test_parameter_id')->constrained()->restrictOnDelete();
            $table->string('parameter_code', 40);
            $table->string('parameter_name', 150);
            $table->string('result_type', 20);
            $table->string('unit_symbol', 40)->nullable();
            $table->decimal('reference_min', 14, 4)->nullable();
            $table->decimal('reference_max', 14, 4)->nullable();
            $table->text('reference_text')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->decimal('numeric_value', 14, 4)->nullable();
            $table->text('text_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->string('flag', 20);
            $table->timestamps();
            $table->unique(['lab_result_id', 'lab_test_parameter_id']);
            $table->index(['lab_result_id', 'flag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_result_values');
        Schema::dropIfExists('lab_results');
    }
};
