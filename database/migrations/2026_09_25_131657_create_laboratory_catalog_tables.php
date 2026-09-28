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
        Schema::create('lab_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status', 'name']);
        });
        Schema::create('lab_sample_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status', 'name']);
        });
        Schema::create('lab_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('symbol', 40);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status', 'name']);
        });
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->foreignId('active_version_id')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status', 'name']);
        });
        Schema::create('lab_test_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('category_id')->constrained('lab_categories')->restrictOnDelete();
            $table->foreignId('sample_type_id')->constrained('lab_sample_types')->restrictOnDelete();
            $table->string('sample_volume', 100)->nullable();
            $table->text('instructions')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('price', 12, 2);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['lab_test_id', 'version']);
            $table->index(['lab_test_id', 'status']);
        });
        Schema::create('lab_test_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_version_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('result_type', 20);
            $table->foreignId('unit_id')->nullable()->constrained('lab_units')->restrictOnDelete();
            $table->decimal('reference_min', 14, 4)->nullable();
            $table->decimal('reference_max', 14, 4)->nullable();
            $table->text('reference_text')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['lab_test_version_id', 'code']);
            $table->index(['lab_test_version_id', 'sort_order']);
        });
        Schema::table('lab_tests', fn (Blueprint $table) => $table->foreign('active_version_id')->references('id')->on('lab_test_versions')->nullOnDelete());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_tests', fn (Blueprint $table) => $table->dropForeign(['active_version_id']));
        Schema::dropIfExists('lab_test_parameters');
        Schema::dropIfExists('lab_test_versions');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('lab_units');
        Schema::dropIfExists('lab_sample_types');
        Schema::dropIfExists('lab_categories');
    }
};
