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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('defrag_news')->default(true);
            $table->boolean('tournament_news')->default(true);
            $table->boolean('invitations')->default(true);

            $table->string('records_vq3')->default('all');
            $table->string('records_cpm')->default('all');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
