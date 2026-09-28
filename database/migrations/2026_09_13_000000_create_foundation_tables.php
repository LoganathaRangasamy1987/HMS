<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
            $table->unique(['branch_id', 'name']);
            $table->index(['hospital_id', 'branch_id', 'status']);
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('user_id');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['user_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('users')->restrictOnDelete();
            $table->unique(['user_id', 'branch_id']);
            $table->index(['hospital_id', 'branch_id', 'status']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('module', 60);
            $table->string('action', 60);
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['hospital_id', 'created_at']);
        });
        Schema::create('private_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->foreign(['branch_id', 'hospital_id'])->references(['id', 'hospital_id'])->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_documents');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('departments');
    }
};
