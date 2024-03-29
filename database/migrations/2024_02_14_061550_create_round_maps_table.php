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
        Schema::create('round_maps', function (Blueprint $table) {
            $table->id();
            $table->integer('round_id');
            $table->string('crc');
            $table->string('name');
            $table->string('download_name');
            $table->string('pk3');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_maps');
    }
};
