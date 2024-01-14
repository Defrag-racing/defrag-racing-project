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
        Schema::create('online_players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('time');
            $table->integer('mdd_id');
            $table->integer('client_id');
            $table->boolean('nospec')->default(false);
            $table->string('country');
            $table->integer('follow_num');
            $table->integer('server_id');
            $table->string('model');
            $table->string('headmodel');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_players');
    }
};
