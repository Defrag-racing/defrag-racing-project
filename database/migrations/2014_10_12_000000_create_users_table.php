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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('plain_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->string('oldhash')->nullable()->default(NULL);
            $table->string('country')->default('_404');
            $table->string('mdd_id')->nullable()->default(NULL);
            $table->boolean('admin')->default(false);

            $table->string('twitter_name')->nullable()->default(NULL);
            $table->string('twitch_name')->nullable()->default(NULL);
            $table->string('discord_name')->nullable()->default(NULL);

            $table->string('model')->default('sarge');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
