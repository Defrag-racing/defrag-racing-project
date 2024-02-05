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
        Schema::table('records', function (Blueprint $table) {
            $table->index('mdd_id');
            $table->index('physics');
            $table->index('gametype');
            $table->index('time');
            $table->index('date_set');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex(['mapname']);
            $table->dropIndex(['mdd_id']);
            $table->dropIndex(['physics']);
            $table->dropIndex(['gametype']);
            $table->dropIndex(['time']);
            $table->dropIndex(['date_set']);
        });
    }
};
