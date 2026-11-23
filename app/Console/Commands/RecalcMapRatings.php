<?php

namespace App\Console\Commands;

use App\Http\Controllers\RankingController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecalcMapRatings extends Command
{
    protected $signature = 'ratings:recalc-map {map : Map name to recalculate} {--physics= : vq3 or cpm (default: both)} {--mode=run : game mode}';
    protected $description = 'Incrementally recalculate ratings for a single map (after new record)';

    const CATEGORIES = ['overall', 'rocket', 'plasma', 'grenade', 'slick', 'tele', 'bfg', 'strafe', 'lg'];

    public function handle()
    {
        $map = $this->argument('map');
        $physics = $this->option('physics');
        $mode = $this->option('mode') ?? 'run';

        $physicsList = $physics ? [$physics] : ['vq3', 'cpm'];

        foreach ($physicsList as $phys) {
            $this->info("Incremental recalc: {$map} ({$phys} {$mode})...");
            $start = microtime(true);

            $rustBinary = storage_path('app/defrag_rating');
            if (!file_exists($rustBinary)) {
                $rustBinary = '/tmp/defrag_rating';
            }

            if (!file_exists($rustBinary)) {
                $this->error("Rust binary not found!");
                return 1;
            }

            $escapedMap = escapeshellarg($map);
            $output = [];
            $returnCode = 0;
            exec("$rustBinary $phys $mode --map=$escapedMap 2>&1", $output, $returnCode);

            $duration = round(microtime(true) - $start, 2);

            if ($returnCode === 0) {
                $this->info("✓ Completed in {$duration}s");
                foreach ($output as $line) {
                    $this->line($line);
                }

                // Flush ranking cache for all categories
                foreach (self::CATEGORIES as $category) {
                    $this->flushRankingCache($phys, $mode, $category);
                }

                Log::info("Incremental ratings recalc", [
                    'map' => $map,
                    'physics' => $phys,
                    'mode' => $mode,
                    'duration' => $duration,
                ]);
            } else {
                $this->error("Failed: " . implode("\n", $output));
                return 1;
            }
        }

        return 0;
    }

    private function flushRankingCache(string $physics, string $mode, string $category): void
    {
        $rankingTypes = ['active_players', 'all_players'];

        foreach ($rankingTypes as $rankingtype) {
            for ($page = 1; $page <= 50; $page++) {
                Cache::forget(RankingController::pageCacheKey($physics, $mode, $rankingtype, $category, $page));
            }
        }

        Cache::forget('ranking:last_recalculation');
    }
}
