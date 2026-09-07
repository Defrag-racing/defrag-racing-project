<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A prize can be given to DefragLive.
 *
 * The site and the next comps were the two places a winner could hand a
 * prize back to. The DefragLive contest pool is the third: money there is a
 * SiteDonation with a defraglive earmark, the same way a comps one carries
 * a comps earmark, so the part gets its own column and its own donation id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_payouts', function (Blueprint $table) {
            $table->decimal('donated_defraglive_eur', 8, 2)->default(0)->after('donated_comps_eur');
            $table->foreignId('defraglive_donation_id')->nullable()->after('comps_donation_id')
                ->constrained('site_donations')->nullOnDelete();
        });

        DB::statement("ALTER TABLE comp_payouts MODIFY status ENUM('pending','paid','donated_site','donated_comps','donated_defraglive','split') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('comp_payouts')->where('status', 'donated_defraglive')->update(['status' => 'donated_site']);

        DB::statement("ALTER TABLE comp_payouts MODIFY status ENUM('pending','paid','donated_site','donated_comps','split') NOT NULL DEFAULT 'pending'");

        Schema::table('comp_payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defraglive_donation_id');
            $table->dropColumn('donated_defraglive_eur');
        });
    }
};
