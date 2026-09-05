<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Jobs\GetLastMddRecords;
use App\Jobs\ScrapeRecords;
use App\Jobs\TournamentCalculationsJob;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {
    // mdd scrapes...
    // scrape online servers every minute
    $schedule->command('scrape:servers 0')->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();
    // scrape offline servers less frequently (every 5 minutes)
    $schedule->command('scrape:servers 1')->withoutOverlapping()->evenInMaintenanceMode()->everyFiveMinutes();
        $schedule->job(new GetLastMddRecords)->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();
        $schedule->job(new ScrapeRecords(1, 2))->withoutOverlapping()->evenInMaintenanceMode()->everyFiveMinutes();
        $schedule->command('scrape:maps')->withoutOverlapping()->evenInMaintenanceMode()->everyTwoMinutes();

        $schedule->job(new TournamentCalculationsJob)->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();

        $schedule->command('tournaments:notifications-send')->everyTwoMinutes();

        // Full rating recalc daily (backup - incremental handles new records in real-time)
        $schedule->command('ratings:calculate')->withoutOverlapping()->daily();

        // Rebuild the server bundle (dfsv-core.tar) when a new oDFe engine build
        // or DeFRaG mod appears; a no-op when nothing changed. The sync right
        // after publishes the fresh archives (size, built_at) to the downloads
        // hub, so it has to run second.
        $schedule->command('bundle:build-server')->withoutOverlapping()->daily();
        $schedule->command('downloads:sync-server-bundle')->withoutOverlapping()->dailyAt('00:30');

        // Update last_activity in player_ratings from records (~20s)
        $schedule->command('ratings:update-activity')->withoutOverlapping()->hourly();

        // Close amnesty requests whose author has since beaten the time: the new
        // run already replaced the old record, so there is nothing left to hide.
        $schedule->command('amnesty:resolve-beaten')->withoutOverlapping()->hourly();

        // Cache WR/Top3 counts for clan statistics (updates cached_wr_count and cached_top3_count on users table)
        $schedule->command('rankings:cache')->withoutOverlapping()->hourly();

        // More often than the counts are kept for, so the Demome Control page
        // always finds them ready. They take about nine seconds to work out
        // and the page used to pay that itself.
        $schedule->command('demome:warm-control')->withoutOverlapping()->everyFifteenMinutes();

        // Check Twitch live status every 2 minutes
        $schedule->command('twitch:check-live-status')->withoutOverlapping()->everyTwoMinutes();

        // Process overdue Headhunter challenge disputes and auto-ban creators
        $schedule->command('headhunter:process-disputes')->withoutOverlapping()->daily();

        // Backstop rematch of all demos against current aliases. Manual alias
        // add/remove now rematches the affected demos immediately (see
        // AliasController + RematchAliasDemosJob), so this full ~360k-demo
        // sweep is only insurance for what the targeted pass can't catch:
        // colour-coded player_names, bulk MDD alias imports, account claims,
        // and creating missing offline_records. Weekly is plenty for that.
        $schedule->command('demos:rematch-all')->withoutOverlapping()->weeklyOn(1, '02:00');

        // Recalculate all player profile stats (WRs, totals, avg rank, etc.) daily at 3am
        $schedule->command('process-all-player-stats')->withoutOverlapping()->dailyAt('03:00');

        // Refresh the /maps/stats cache. ~15s, run after the player-stats
        // recalc so the page never serves a cold cache to a visitor.
        $schedule->command('mapstats:rebuild')->withoutOverlapping()->dailyAt('04:00');

        // Auto-prune api_call_log rows older than 30 days. The retention
        // policy lives on the App\Models\ApiCallLog::prunable() scope -
        // model:prune calls that and deletes whatever matches.
        $schedule->command('model:prune', ['--model' => [\App\Models\ApiCallLog::class]])
            ->withoutOverlapping()
            ->dailyAt('03:30');

        // Unlock demos stuck in 'processing' for more than 15 minutes and re-queue them
        $schedule->command('demos:unlock-stuck')->withoutOverlapping()->everyFiveMinutes();

        // Rebuild the public DefragLive console.json/serverstate.json from the
        // DB every minute - same cadence as the old cron `docker cp` out of the
        // bridge container it replaces. No-ops until DEFRAGLIVE_PUBLIC_PATH is
        // set (the VPS cutover), so it can't fight the legacy cron before then.
        $schedule->command('defraglive:write-json')->withoutOverlapping()->everyMinute();

        // Close DefragLive watch sessions left open after the bot goes offline,
        // so dangling sessions don't accrue dead time toward the contest.
        $schedule->command('defraglive:close-stale-sessions')->withoutOverlapping()->everyMinute();

        // Keep the $5 / 2-week DefragLive contest cadence rolling (close ended
        // windows, open the next). No-ops until the admin seeds the first one.
        $schedule->command('defraglive:rollover-contests')->withoutOverlapping()->hourly();

        // Comps: close ballots, start and finish rounds, open the next week's
        // vote. Every minute because the boundaries are exact times (Sunday
        // 20:00 Prague) and a round should not sit open past its own deadline.
        // No-ops entirely until comps_weekly_enabled is turned on in admin.
        $schedule->command('comps:tick')->withoutOverlapping()->everyMinute();

        // Auto-fill lat/lon from IP for any server missing it (drives the
        // visitor ping estimate). Daily is plenty - new servers are rare and a
        // ping badge appearing within a day is fine. No-op once all servers are
        // geolocated (a cheap "WHERE latitude IS NULL" returning nothing); never
        // overwrites existing coordinates.
        $schedule->command('servers:geolocate')->withoutOverlapping()->daily();

        // Serverdemos upload monitoring: pulls per-SFTP-account demo counts
        // and newest-upload timestamps from the storage VPS ingest tree into
        // sftp_credentials, driving the health badge in Filament.
        $schedule->command('serverdemos:sync-stats')->withoutOverlapping()->hourly();

        // Keeps the server_demos index in step with the store on the storage
        // VPS - the admin browser reads the table, not SFTP, so a demo is
        // invisible until this has run. Walking all 116k files takes ~14 s
        // and unchanged rows are skipped, so every 15 minutes is affordable.
        $schedule->command('serverdemos:index')->withoutOverlapping()->everyFifteenMinutes();

        // Once a night the same walk runs with --sweep, which is what flags
        // demos that are no longer on the local disk. The frequent runs skip
        // unchanged rows, so they cannot tell "unchanged" from "missing" and
        // must not draw that conclusion.
        $schedule->command('serverdemos:index --sweep')->withoutOverlapping()->dailyAt('05:00');

        // Auto-populate demome render queue when idle (tiered rotation, tops up to 5)
        $schedule->command('demome:populate-queue')->withoutOverlapping()->everyTenMinutes();

        // Nightly full rebuild of the materialized Demos Top ranking
        // (demos_top_ranks). Incremental per-map rebuilds run inline on new
        // records/demos via RebuildDemosTopRanksJob; this is the safety net
        // that heals any group missed by an invalidation hook.
        $schedule->command('demos:rebuild-top-ranks')->withoutOverlapping()->dailyAt('04:30');

        // Pre-warm RenderQueueService::getPoolCounts() so the /defraghq/render-queue-preview
        // and /defraghq/demome-control pages never hit a cold cache. The query is
        // ~8 large JOIN + NOT EXISTS aggregates against rendered_videos / records /
        // uploaded_demos that easily stretch past Cloudflare's 100s timeout on
        // first visit after the 30-min TTL expires. Force a fresh rebuild every
        // 20 minutes so the cache is continuously valid and an admin visit
        // never has to wait on the rebuild itself.
        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::forget('demome:pool_counts');
            for ($tier = 1; $tier <= 8; $tier++) {
                \Illuminate\Support\Facades\Cache::forget("tier_pool_count_{$tier}");
            }
            \App\Services\RenderQueueService::getPoolCounts();
        })->name('warm-pool-counts')->withoutOverlapping()->cron('*/20 * * * *');

        // NOTE: Triweekly auto-publish scheduler removed. Bulk publishing is now manual-only
        // via the per-tier buttons in Filament DemomeControl page. The Python bot's every-4h
        // deficit check (auto_publish_deficit) still keeps a steady 12 videos/day trickle.

        // Rebuild records page cache every 12 hours (full consistency refresh)
        $schedule->command('records:rebuild-cache')->withoutOverlapping()->twiceDaily(6, 18);

        // Map ranked flags are now updated by ratings:calculate (Rust binary)

        // Calculate community helper leaderboard scores every 30 minutes
        $schedule->command('community:calculate-scores')->withoutOverlapping()->everyThirtyMinutes();

        // Check q3defrag.org for new DeFRaG mod releases every Sunday at 12:00
        $schedule->command('wiki:check-defrag-releases')->withoutOverlapping()->weeklyOn(0, '12:00');

        // Mirror those same releases into the downloads hub, whose DeFRaG mod
        // category renders them as a self-updating article.
        $schedule->command('downloads:sync-defrag-mod')->withoutOverlapping()->weeklyOn(0, '12:15');

        // Refresh the size and date on the family-friendly pk3 set. The files
        // rarely change, so weekly is plenty.
        $schedule->command('downloads:sync-family-friendly')->withoutOverlapping()->weeklyOn(0, '12:30');

        // Prune guest sessions older than 24h so bot traffic doesn't bloat
        // the sessions table. Logged-in sessions keep the 30-day lifetime.
        $schedule->call(function () {
            DB::table('sessions')
                ->whereNull('user_id')
                ->where('last_activity', '<', time() - 86400)
                ->delete();
        })->daily()->name('prune-guest-sessions')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
