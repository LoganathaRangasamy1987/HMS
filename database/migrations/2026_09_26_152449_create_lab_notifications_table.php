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
        Schema::create('lab_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 191);
            $table->string('type', 40);
            $table->string('title', 150);
            $table->string('message', 500);
            $table->string('url', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['recipient_user_id', 'event_key']);
            $table->index(['recipient_user_id', 'read_at', 'created_at']);
            $table->index(['branch_id', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_notifications');
    }
};
