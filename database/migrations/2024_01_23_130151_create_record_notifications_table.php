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
        Schema::create('record_notifications', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('name');
            $table->string('country');
            $table->string('physics');
            $table->string('mode');
            $table->integer('time');
            $table->integer('mdd_id');
            $table->integer('record_player_id')->nullable()->default(NULL);
            $table->string('mapname');
            $table->datetime('date_set');
            $table->boolean('read')->default(false);
            $table->integer('my_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_notifications');
    }
};
