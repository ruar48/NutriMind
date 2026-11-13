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
        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('learning_module_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('grade_level');
            $table->string('topic')->nullable();
            $table->string('competency_code', 40)->nullable();
            $table->string('type');
            $table->text('prompt');
            $table->json('choices')->nullable();
            $table->string('correct_answer', 500);
            $table->text('explanation')->nullable();
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('difficulty')->default('easy');
            $table->boolean('is_shared')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamps();

            $table->index(['subject_id', 'grade_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_bank_items');
    }
};
