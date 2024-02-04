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
        Schema::create('mdd_profiles', function (Blueprint $table) {
            $table->integer('id');
            $table->primary('id');

            $table->integer('user_id')->nullable()->default(NULL);

            $table->string('name');
            $table->string('plain_name');
            $table->string('country')->default('_404');

            $table->string('model')->default('sarge');
            $table->string('headmodel')->default('sarge');

            $table->integer('cpm_total_records')->default(0);
            $table->integer('vq3_total_records')->default(0);

            $table->integer('cpm_ctf_records')->default(0);
            $table->integer('vq3_ctf_records')->default(0);

            $table->float('cpm_average_rank')->default(0);
            $table->float('vq3_average_rank')->default(0);

            $table->integer('cpm_world_records')->default(0);
            $table->integer('vq3_world_records')->default(0);

            $table->integer('cpm_strafe_records')->default(0);
            $table->integer('cpm_rocket_records')->default(0);
            $table->integer('cpm_grenade_records')->default(0);
            $table->integer('cpm_plasma_records')->default(0);
            $table->integer('cpm_bfg_records')->default(0);

            $table->integer('vq3_strafe_records')->default(0);
            $table->integer('vq3_rocket_records')->default(0);
            $table->integer('vq3_grenade_records')->default(0);
            $table->integer('vq3_plasma_records')->default(0);
            $table->integer('vq3_bfg_records')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdd_profiles');
    }
};
