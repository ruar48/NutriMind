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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('lrn', 12)->nullable()->unique();
            $table->unsignedTinyInteger('grade_level');
            $table->string('sex')->nullable();
            $table->date('birthdate')->nullable();
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedSmallInteger('level')->default(1);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
