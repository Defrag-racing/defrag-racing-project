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
        Schema::create('maps', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->index();
            $table->string('description');
            $table->string('author');
            $table->string('pk3');
            $table->integer('pk3_size');
            $table->string('thumbnail');
            $table->string('physics');
            $table->string('gametype');
            $table->string('mod');
            $table->string('weapons');
            $table->string('items');
            $table->string('functions');
            $table->boolean('visible');
            $table->datetime('date_added');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maps');
    }
};
