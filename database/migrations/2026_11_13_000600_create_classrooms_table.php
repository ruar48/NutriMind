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
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('grade_level');
            $table->string('section');
            $table->string('room')->nullable();
            $table->text('description')->nullable();
            $table->string('class_code', 8)->unique();
            $table->string('learning_mode')->default('automatic');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'archived_at']);
            $table->index('subject_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
