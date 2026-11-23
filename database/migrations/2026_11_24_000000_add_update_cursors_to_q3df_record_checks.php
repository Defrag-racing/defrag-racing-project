<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the update step of an import stopped, so the next job goes on
     * from there: the last map name and the last mdd id it finished.
     */
    public function up(): void
    {
        Schema::table('q3df_record_checks', function (Blueprint $table) {
            $table->string('maps_cursor')->nullable()->after('imported');
            $table->unsignedInteger('profiles_cursor')->nullable()->after('maps_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('q3df_record_checks', function (Blueprint $table) {
            $table->dropColumn(['maps_cursor', 'profiles_cursor']);
        });
    }
};
