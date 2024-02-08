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
        Schema::create('oldtop_records', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plain_name');
            $table->string('mapname');
            $table->integer('time');
            $table->string('gametype');
            $table->integer('physic');
            $table->integer('rank');
            $table->string('country');
            $table->datetime('date_set');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oldtop_records');
    }
};
