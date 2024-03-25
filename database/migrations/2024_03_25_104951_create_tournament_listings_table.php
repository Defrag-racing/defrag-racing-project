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
        Schema::create('tournament_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('image')->nullable()->default(null);
            $table->string('link')->nullable()->default(null);
            $table->string('type')->default('tournament');
            $table->string('subtype')->default('');
            $table->integer('tournament_id');
            $table->integer('round_id')->nullable()->default(null);
            $table->integer('order');
            $table->boolean('system')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_listings');
    }
};
