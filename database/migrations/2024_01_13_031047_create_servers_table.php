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
            $table->string('admin_contact');
            $table->string('ping_url');
            $table->boolean('offline')->default(true);
            $table->boolean('visible')->default(true);
            $table->string('map');
            $table->string('defrag');
            $table->string('rconpassword');
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
