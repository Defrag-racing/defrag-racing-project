<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An override belongs to one leaderboard, not to a map and physics.
 *
 * The column is `gamemode` and not `mode`, because `mode` on this table already
 * means allow or block.
 *
 * A map's ctf2 fastcaps and its ordinary runs are separate lists with separate
 * records, and one being a joke says nothing about the other. Existing rows
 * are ordinary runs, which is all there was when they were made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_render_overrides', function (Blueprint $table) {
            $table->string('gamemode', 16)->default('run')->after('physics');
        });

        Schema::table('map_render_overrides', function (Blueprint $table) {
            $table->dropUnique('map_render_override_unique');
            $table->unique(['map_name', 'physics', 'gamemode'], 'map_render_override_unique');
        });
    }

    public function down(): void
    {
        Schema::table('map_render_overrides', function (Blueprint $table) {
            $table->dropUnique('map_render_override_unique');
            $table->dropColumn('gamemode');
        });

        Schema::table('map_render_overrides', function (Blueprint $table) {
            $table->unique(['map_name', 'physics'], 'map_render_override_unique');
        });
    }
};
