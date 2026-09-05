<?php

namespace App\Console\Commands;

use App\Services\JokeMaps;
use App\Services\RenderQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Work the Demome Control numbers out ahead of time.
 *
 * The eight pool counts take about nine seconds even with the right indexes,
 * and they are kept for thirty minutes. Without this, whoever opens the page
 * first after that half hour is over pays for all of it while the page sits
 * there, and on a busy box that was a Cloudflare 504 rather than a slow load.
 *
 * Run more often than the cache lives, so the numbers are always already
 * there. Nothing here changes anything: it only asks the questions the page
 * would have asked.
 */
class WarmDemomeControl extends Command
{
    protected $signature = 'demome:warm-control';

    protected $description = 'Work out the Demome Control counts in the background so the page never has to.';

    public function handle(): int
    {
        $started = microtime(true);

        // Ordered the way the page needs them: the blocked map list feeds the
        // pool counts, so working it out first keeps the counts from doing it.
        foreach (RenderQueueService::TIER_LABELS as $tier => $label) {
            Cache::forget("tier_pool_count_{$tier}");
        }

        foreach (['demome:joke_map_pairs', 'demome:pool_counts', 'demome:backlog_stats'] as $key) {
            Cache::forget($key);
        }

        JokeMaps::pairs();
        RenderQueueService::getPoolCounts();

        $this->info('Demome Control counts worked out in ' . round(microtime(true) - $started, 1) . 's.');

        return self::SUCCESS;
    }
}
