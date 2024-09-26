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
        Schema::create('players_ratings', function (Blueprint $table) {
            $table->string('name');
            $table->integer('mdd_id');
            $table->integer('user_id');
            $table->string('physics');
            $table->string('mode');
            $table->integer('player_rating');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players_ratings');
    }
};
