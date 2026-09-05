<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last word over the joke map rule, one map and physics at a time.
 *
 * The rule counts players on a record time, and a count can be wrong in both
 * directions: a hard map can end up with three people on the same time, and a
 * map built to hand out first place can sit just under the bar. Neither is
 * fixable by moving the number, because moving it moves every other map too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_render_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('map_name');
            $table->string('physics', 16);
            // `allow` renders it whatever the rule says. `block` refuses it
            // whatever the rule says.
            $table->string('mode', 8);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['map_name', 'physics'], 'map_render_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_render_overrides');
    }
};
