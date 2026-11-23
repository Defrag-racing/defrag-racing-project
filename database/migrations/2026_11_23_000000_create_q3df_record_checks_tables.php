<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A records check compares q3df.org with the records table, for a range of
     * dates or for the whole history, and keeps every difference it found so
     * the admin can look at them and import the missing ones.
     */
    public function up(): void
    {
        Schema::create('q3df_record_checks', function (Blueprint $table) {
            $table->id();
            // range: one date range, read newest first. full: the whole archive.
            $table->string('scope', 10);
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            // queued, running, done, failed, importing, imported
            $table->string('status', 12)->default('queued');
            $table->unsignedInteger('pages_read')->default(0);
            $table->unsignedInteger('pages_total')->nullable();
            $table->unsignedInteger('q3df_rows')->default(0);
            $table->unsignedInteger('matching')->default(0);
            $table->unsignedInteger('missing')->default(0);
            $table->unsignedInteger('different')->default(0);
            $table->unsignedInteger('only_here')->nullable();
            $table->unsignedInteger('imported')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('q3df_record_check_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained('q3df_record_checks')->cascadeOnDelete();
            // missing: q3df has it, we do not. different: we hold another time.
            $table->string('kind', 10);
            $table->unsignedInteger('mdd_id');
            $table->string('name');
            $table->string('country', 10)->nullable();
            $table->string('mapname');
            $table->string('physics', 10);
            $table->string('mode', 10);
            $table->unsignedInteger('q3df_time');
            $table->unsignedInteger('our_time')->nullable();
            $table->dateTime('date_set');
            $table->timestamp('imported_at')->nullable();

            $table->index(['check_id', 'kind']);
            $table->index(['check_id', 'date_set']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('q3df_record_check_rows');
        Schema::dropIfExists('q3df_record_checks');
    }
};
