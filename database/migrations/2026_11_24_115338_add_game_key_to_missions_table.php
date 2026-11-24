<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The game key is the mission ID the Unity game uses ("G5-ENG-M1"). The game's progress sync finds the
     * mission by it, so a mission without one never receives progress from the game.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->string('game_key', 40)->nullable()->unique()->after('learning_module_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropUnique(['game_key']);
            $table->dropColumn('game_key');
        });
    }
};
