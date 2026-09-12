<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defraglive_contests', function (Blueprint $table) {
            // "Best of three" draw: the three drawn ticket numbers and who
            // held each one, in draw order. The holder with the most watch
            // time among them wins. Null on contests drawn under the old
            // single-ticket rule (winning_ticket still holds their number).
            $table->json('draw_picks')->nullable()->after('winning_ticket');
        });
    }

    public function down(): void
    {
        Schema::table('defraglive_contests', function (Blueprint $table) {
            $table->dropColumn('draw_picks');
        });
    }
};
