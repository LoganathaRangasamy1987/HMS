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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Asia/Kolkata')->after('city');
        });
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('slot_duration_minutes');
            $table->unsignedSmallInteger('capacity_per_slot')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['doctor_profile_id', 'day_of_week', 'starts_at']);
            $table->index(['doctor_profile_id', 'day_of_week', 'status']);
        });
        Schema::create('doctor_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['doctor_profile_id', 'starts_on', 'ends_on'], 'doctor_closure_dates_index');
        });
        Schema::create('doctor_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('availability', 20);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedSmallInteger('slot_duration_minutes')->nullable();
            $table->unsignedSmallInteger('capacity_per_slot')->nullable();
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['doctor_profile_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_exceptions');
        Schema::dropIfExists('doctor_unavailabilities');
        Schema::dropIfExists('doctor_schedules');
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
