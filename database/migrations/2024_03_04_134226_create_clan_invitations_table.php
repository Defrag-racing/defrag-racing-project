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
        Schema::create('clan_invitations', function (Blueprint $table) {
            $table->id();
            $table->integer('clan_id');
            $table->integer('user_id');
            $table->boolean('accepted')->default(false);
            $table->string('type')->default('invite');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clan_invitations');
    }
};
