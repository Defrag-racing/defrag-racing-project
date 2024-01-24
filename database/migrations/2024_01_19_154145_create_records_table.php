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
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country');
            $table->string('physics');
            $table->string('mode');
            $table->string('gametype');
            $table->integer('time');
            $table->datetime('date_set');
            $table->integer('mdd_id');
            $table->integer('user_id')->nullable()->default(NULL);
            $table->string('mapname')->index();
            $table->integer('rank')->default(1);
            $table->integer('besttime')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
