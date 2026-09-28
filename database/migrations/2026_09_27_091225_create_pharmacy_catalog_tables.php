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
        Schema::create('pharmacy_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'name']);
            $table->index(['hospital_id', 'status']);
        });
        Schema::create('medicine_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'name']);
        });
        Schema::create('medicine_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('symbol', 30);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'name']);
            $table->unique(['hospital_id', 'symbol']);
        });
        Schema::create('pharmacy_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('tax_registration_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status', 'name']);
        });
        Schema::table('medicines', function (Blueprint $table) {
            $table->foreignId('pharmacy_manufacturer_id')->nullable()->after('generic_name')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_type_id')->nullable()->after('pharmacy_manufacturer_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->after('strength')->constrained('medicine_units')->restrictOnDelete();
            $table->foreignId('sale_unit_id')->nullable()->after('purchase_unit_id')->constrained('medicine_units')->restrictOnDelete();
            $table->decimal('reorder_level', 12, 3)->default(0)->after('sale_unit_id');
            $table->decimal('tax_rate_percent', 5, 2)->default(0)->after('reorder_level');
            $table->boolean('prescription_required')->default(true)->after('tax_rate_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pharmacy_manufacturer_id');
            $table->dropConstrainedForeignId('medicine_type_id');
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropConstrainedForeignId('sale_unit_id');
            $table->dropColumn(['reorder_level', 'tax_rate_percent', 'prescription_required']);
        });
        Schema::dropIfExists('pharmacy_suppliers');
        Schema::dropIfExists('medicine_units');
        Schema::dropIfExists('medicine_types');
        Schema::dropIfExists('pharmacy_manufacturers');
    }
};
