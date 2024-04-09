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
        Schema::table('round_maps', function (Blueprint $table) {
            $table->string('download_name')->default('map.pk3');
            $table->boolean('external')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('round_maps', function (Blueprint $table) {
            $table->dropColumn('download_name');
            $table->dropColumn('external');
        });
    }
};