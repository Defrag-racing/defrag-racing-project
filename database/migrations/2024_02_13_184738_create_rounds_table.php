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
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mapname');
            $table->string('category');
            $table->string('image');
            $table->string('author');
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->integer('tournament_id');
            $table->string('weapons');
            $table->string('items');
            $table->string('functions');
            $table->boolean('results')->default(false);
            $table->boolean('published')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
