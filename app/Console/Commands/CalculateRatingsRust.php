<?php

namespace App\Console\Commands;

use App\Http\Controllers\RankingController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CalculateRatingsRust extends Command
{
    protected $signature = 'ratings:calculate {--physics= : vq3 or cpm} {--mode=run : game mode} {--category= : ranking category}';
    protected $description = 'Calculate ratings using Rust (100x faster than SQL)';

    // All available categories for run mode
    const CATEGORIES = ['overall', 'rocket', 'plasma', 'grenade', 'slick', 'tele', 'bfg', 'strafe', 'lg'];
    const CTF_MODES = ['ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'];
    const RANKING_TYPES = ['active_players', 'all_players'];

    public function handle()
    {
        $physics = $this->option('physics');
        $mode = $this->option('mode') ?? 'run';
        $category = $this->option('category');

        if (!$physics) {
            // Run for both VQ3 and CPM, all modes
            $this->info('Calculating ratings for VQ3 and CPM, all modes and categories...');

            $this->call('ratings:calculate', ['--physics' => 'vq3', '--mode' => 'run']);
            $this->call('ratings:calculate', ['--physics' => 'cpm', '--mode' => 'run']);

            // CTF modes - overall only
            foreach (self::CTF_MODES as $ctfMode) {
                $this->call('ratings:calculate', ['--physics' => 'vq3', '--mode' => $ctfMode, '--category' => 'overall']);
                $this->call('ratings:calculate', ['--physics' => 'cpm', '--mode' => $ctfMode, '--category' => 'overall']);
            }

            return 0;
        }

        if (!$category) {
            // Run all categories for this physics/mode
            $this->info("Calculating all categories for {$physics} {$mode}...");
            $categories = in_array($mode, self::CTF_MODES) ? ['overall'] : self::CATEGORIES;
            foreach ($categories as $cat) {
                $this->call('ratings:calculate', ['--physics' => $physics, '--mode' => $mode, '--category' => $cat]);
            }
            return 0;
        }

        $this->info("Calculating {$physics} {$mode} [{$category}] ratings using Rust...");
        $start = microtime(true);

        // Path to Rust binary (production: storage/app, docker: /tmp)
        $rustBinary = storage_path('app/defrag_rating');
        if (!file_exists($rustBinary)) {
            $rustBinary = '/tmp/defrag_rating'; // Fallback for Docker
        }

        if (!file_exists($rustBinary)) {
            $this->error("Rust binary not found! Run: ./build-rust.sh");
            return 1;
        }

        // Execute Rust binary with category
        $output = [];
        $returnCode = 0;
        exec("$rustBinary $physics $mode $category 2>&1", $output, $returnCode);

        $duration = round(microtime(true) - $start, 2);

        if ($returnCode === 0) {
            $this->info("✓ Completed in {$duration}s");
            foreach ($output as $line) {
                $this->line($line);
            }

            // Flush ranking page cache for this physics/mode/category
            $this->flushRankingCache($physics, $mode, $category);

            Log::info("Rust ratings calculated", [
                'physics' => $physics,
                'mode' => $mode,
                'category' => $category,
                'duration' => $duration
            ]);

            return 0;
        } else {
            $this->error("Failed to calculate ratings");
            foreach ($output as $line) {
                $this->error($line);
            }

            Log::error("Rust ratings calculation failed", [
                'physics' => $physics,
                'mode' => $mode,
                'category' => $category,
                'output' => implode("\n", $output)
            ]);

            return 1;
        }
    }

    /**
     * Flush ranking page cache after recalculation.
     * Uses Redis SCAN via Cache::forget for known key patterns.
     */
    private function flushRankingCache(string $physics, string $mode, string $category): void
    {
        $flushed = 0;

        foreach (self::RANKING_TYPES as $rankingtype) {
            for ($page = 1; $page <= 50; $page++) {
                $key = RankingController::pageCacheKey($physics, $mode, $rankingtype, $category, $page);
                Cache::forget($key);
                $flushed++;
            }
        }

        Cache::forget('ranking:last_recalculation');

        $this->line("  Cache flushed ({$flushed} keys)");

        // Rebuild first 3 pages for this physics/mode/category
        $controller = new RankingController();
        foreach (self::RANKING_TYPES as $rankingtype) {
            for ($page = 1; $page <= RankingController::PREBUILT_PAGES; $page++) {
                $cacheKey = RankingController::pageCacheKey($physics, $mode, $rankingtype, $category, $page);
                $data = $controller->fetchPageFromDb($physics, $mode, $rankingtype, $category, $page);
                Cache::put($cacheKey, $data, RankingController::CACHE_TTL);
            }
        }

        $this->line("  Cache rebuilt (first " . RankingController::PREBUILT_PAGES . " pages)");
    }
}
