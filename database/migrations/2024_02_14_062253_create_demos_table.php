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
        Schema::create('demos', function (Blueprint $table) {
            $table->id();
            $table->integer('round_id');
            $table->integer('user_id');

            $table->string('file');
            $table->string('filename');

            $table->integer('time');
            $table->integer('rank');
            $table->string('physics');
            $table->integer('points');

            $table->boolean('approved')->default(false);
            $table->boolean('rejected')->default(false);
            $table->boolean('counted')->default(true);
            $table->string('reason')->nullable()->default(null);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demos');
    }
};
