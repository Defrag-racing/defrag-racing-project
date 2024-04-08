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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('slug')->unique();
            $table->string('image');
            $table->string('banner')->nullable()->default(null);
            $table->datetime('start_date');
            $table->datetime('end_date')->nullable()->default(null);
            $table->boolean('published')->default(false);
            $table->boolean('approved')->default(false);
            $table->boolean('has_teams')->default(true);
            $table->longText('rules')->nullable()->default(null);
            $table->integer('prize_pool')->default(0);
            $table->string('trailer')->nullable()->default(null);

            $table->boolean('has_donations')->default(false);

            $table->integer('streamer_window')->default(1);

            $table->integer('creator');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
