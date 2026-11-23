<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a row could not be imported. A row with a reason is not offered for
     * import again, so the button does not keep offering what cannot go in.
     */
    public function up(): void
    {
        Schema::table('q3df_record_check_rows', function (Blueprint $table) {
            $table->string('skipped_reason')->nullable()->after('imported_at');
        });
    }

    public function down(): void
    {
        Schema::table('q3df_record_check_rows', function (Blueprint $table) {
            $table->dropColumn('skipped_reason');
        });
    }
};
