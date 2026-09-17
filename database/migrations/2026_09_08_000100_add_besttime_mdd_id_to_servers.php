<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The holder of a server's best time gets their q3df id in a column of
 * its own.
 *
 * besttime_url held either the linked account's id or, for a player
 * without one, their q3df id - two different numbers in one column, with
 * nothing to say which. The card built /profile/{id} from it either way,
 * so an unlinked holder's link went to whoever had that user id, and the
 * country lookup borrowed that user's flag. The user id stays in
 * besttime_url; the q3df id lives here, and the next scrape fills it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('besttime_mdd_id')->nullable()->after('besttime_url');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('besttime_mdd_id');
        });
    }
};
