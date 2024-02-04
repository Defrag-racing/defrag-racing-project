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
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();

            $table->integer('mdd_id');

            $table->integer('total_records')->default(0);

            $table->integer('cpm_records')->default(0);
            $table->integer('vq3_records')->default(0);

            $table->integer('cpm_ctf_records')->default(0);
            $table->integer('vq3_ctf_records')->default(0);

            $table->float('average_rank')->default(0);
            $table->integer('world_records')->default(0);

            $table->integer('strafe_records')->default(0);
            $table->integer('rocket_records')->default(0);
            $table->integer('grenade_records')->default(0);
            $table->integer('plasma_records')->default(0);
            $table->integer('bfg_records')->default(0);
            $table->integer('strafe_records')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
