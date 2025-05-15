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
        Schema::table('servers', function (Blueprint $table) {
            $table->renameColumn('offline', 'online');
        });

        DB::table('servers')->update([
            'online' => DB::raw('NOT online')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->renameColumn('online', 'offline');
        });

        DB::table('servers')->update([
            'offline' => DB::raw('NOT offline')
        ]);
    }
};
