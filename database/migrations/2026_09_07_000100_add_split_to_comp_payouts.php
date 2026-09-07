<?php

use App\Models\CompPayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A prize can be settled in parts.
 *
 * "Paid out" or "donated" was the whole amount, one or the other. Winners
 * asked for the obvious third thing: take some, give the rest back. So the
 * row now says how many euro went each way, and a settlement that went more
 * than one way is `split`. A given-back part still becomes a real
 * SiteDonation, one per pot, which is why the comps part needs a donation id
 * of its own beside the site one.
 *
 * The three amounts are filled in for every row already settled, from its
 * status, so a row's parts always add up to its amount whichever way it was
 * closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_payouts', function (Blueprint $table) {
            $table->decimal('paid_eur', 8, 2)->default(0)->after('amount');
            $table->decimal('donated_site_eur', 8, 2)->default(0)->after('paid_eur');
            $table->decimal('donated_comps_eur', 8, 2)->default(0)->after('donated_site_eur');
            $table->foreignId('comps_donation_id')->nullable()->after('site_donation_id')
                ->constrained('site_donations')->nullOnDelete();
        });

        DB::statement("ALTER TABLE comp_payouts MODIFY status ENUM('pending','paid','donated_site','donated_comps','split') NOT NULL DEFAULT 'pending'");

        DB::table('comp_payouts')->where('status', CompPayout::STATUS_PAID)->update(['paid_eur' => DB::raw('amount')]);
        DB::table('comp_payouts')->where('status', CompPayout::STATUS_DONATED_SITE)->update(['donated_site_eur' => DB::raw('amount')]);
        DB::table('comp_payouts')->where('status', CompPayout::STATUS_DONATED_COMPS)->update([
            'donated_comps_eur' => DB::raw('amount'),
            'comps_donation_id' => DB::raw('site_donation_id'),
            'site_donation_id' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('comp_payouts')->where('status', 'split')->update(['status' => CompPayout::STATUS_PAID]);
        DB::table('comp_payouts')->where('status', CompPayout::STATUS_DONATED_COMPS)
            ->update(['site_donation_id' => DB::raw('comps_donation_id')]);

        Schema::table('comp_payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comps_donation_id');
            $table->dropColumn(['paid_eur', 'donated_site_eur', 'donated_comps_eur']);
        });

        DB::statement("ALTER TABLE comp_payouts MODIFY status ENUM('pending','paid','donated_site','donated_comps') NOT NULL DEFAULT 'pending'");
    }
};
