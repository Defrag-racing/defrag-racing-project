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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip');
            $table->integer('port');
            $table->string('location');
            $table->string('type');
            $table->string('admin_name');
            $table->string('admin_contact')->nullable()->default(null);
            $table->string('ping_url')->nullable()->default(null);
            $table->boolean('offline')->default(true);
            $table->boolean('visible')->default(true);
            $table->string('map');
            $table->string('besttime_country');
            $table->string('besttime_name')->nullable()->default(NULL);
            $table->string('besttime_url');
            $table->integer('besttime_time');
            $table->string('defrag');
            $table->string('defrag_gametype')->default('5');
            $table->string('rconpassword')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
