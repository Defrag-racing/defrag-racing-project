<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Pagination\LengthAwarePaginator;

use App\Models\PlayerRating;
use App\Models\RatingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RankingController extends Controller
{
    const PAGINATION_LIMIT = 50;
    const ACTIVE_PLAYERS_MONTHS = 3; //keep in sync with one in CalculateRatings.php
    const PREBUILT_PAGES = 3;
    const CACHE_TTL = 43200; // 12 hours
    const CACHE_TTL_RECALCULATION = 300;

    public function index(Request $request) {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        $gametype = $request->input('gametype', 'run');
        $rankingtype = $request->input('rankingtype', 'active_players');
        $category = $request->input('category', 'overall');

        $lastRecalculation = Cache::remember('ranking:last_recalculation', self::CACHE_TTL_RECALCULATION, function () {
            return DB::table('player_ratings')->max('updated_at');
        });

        // On full page load, send immediately without data (lazy load from frontend)
        if (!$isPartial) {
            return Inertia::render('RankingView')
                ->with('vq3Ratings', null)
                ->with('cpmRatings', null)
                ->with('myVq3Rating', null)
                ->with('myCpmRating', null)
                ->with('lastRecalculation', $lastRecalculation);
        }

        // Partial reload: serve from cache
        $vq3Page = max(1, (int) $request->input('vq3Page', 1));
        $cpmPage = max(1, (int) $request->input('cpmPage', 1));

        $vq3Ratings = $this->getCachedPage('vq3', $gametype, $rankingtype, $category, $vq3Page);
        $cpmRatings = $this->getCachedPage('cpm', $gametype, $rankingtype, $category, $cpmPage);

        $myVq3Rating = $this->getMyRating($request, 'vq3', $gametype, $rankingtype, $category);
        $myCpmRating = $this->getMyRating($request, 'cpm', $gametype, $rankingtype, $category);

        // Handle pagination overflow
        if ($vq3Ratings && $request->has('vq3Page') && $request->get('vq3Page') > $vq3Ratings->lastPage()) {
            return redirect()->route('ranking', ['vq3Page' => $vq3Ratings->lastPage()]);
        }
        if ($cpmRatings && $request->has('cpmPage') && $request->get('cpmPage') > $cpmRatings->lastPage()) {
            return redirect()->route('ranking', ['cpmPage' => $cpmRatings->lastPage()]);
        }

        return Inertia::render('RankingView')
            ->with('vq3Ratings', $vq3Ratings)
            ->with('cpmRatings', $cpmRatings)
            ->with('myVq3Rating', $myVq3Rating)
            ->with('myCpmRating', $myCpmRating)
            ->with('lastRecalculation', $lastRecalculation);
    }

    /**
     * Get a cached page. Pages 1-3 are prebuilt (12h cache), pages 4+ are cached on access (12h).
     */
    private function getCachedPage(string $physics, string $gametype, string $rankingtype, string $category, int $page): LengthAwarePaginator
    {
        $cacheKey = "ranking:{$physics}:{$gametype}:{$rankingtype}:{$category}:{$page}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($physics, $gametype, $rankingtype, $category, $page) {
            return $this->fetchPageFromDb($physics, $gametype, $rankingtype, $category, $page);
        });
    }

    /**
     * Fetch a page of ratings from DB.
     */
    public function fetchPageFromDb(string $physics, string $gametype, string $rankingtype, string $category, int $page = 1): LengthAwarePaginator
    {
        $query = PlayerRating::query();
        $columnToChange = '';

        if ($rankingtype === 'active_players') {
            $query->where('last_activity', '>=', now()->subMonths(self::ACTIVE_PLAYERS_MONTHS))
                  ->where('active_players_rank', '>', 0);
            $columnToChange = 'active_players_rank';
        } elseif ($rankingtype === 'all_players') {
            $columnToChange = 'all_players_rank';
        }

        $paginator = $query
            ->with(['user', 'mddProfile'])
            ->where('physics', $physics)
            ->where('mode', $gametype)
            ->where('category', $category)
            ->orderBy($columnToChange, 'ASC')
            ->paginate(self::PAGINATION_LIMIT, ['*'], $physics . 'Page', $page)
            ->withQueryString();

        $paginator->getCollection()->transform(function ($item) use ($columnToChange) {
            $item->rank = $item->$columnToChange;
            unset($item->$columnToChange);
            return $item;
        });

        return $paginator;
    }

    private function getMyRating(Request $request, string $physics, string $gametype, string $rankingtype, string $category = 'overall')
    {
        if (!$request->user() || !$request->user()->mdd_id) {
            return null;
        }

        $query = PlayerRating::query();

        if ($rankingtype === 'active_players') {
            $query->where('last_activity', '>=', now()->subMonths(self::ACTIVE_PLAYERS_MONTHS))
                  ->where('active_players_rank', '>', 0);
        }

        return $query
            ->where('mdd_id', $request->user()->mdd_id)
            ->where('physics', $physics)
            ->where('mode', $gametype)
            ->where('category', $category)
            ->with(['user', 'mddProfile'])
            ->first();
    }

    /**
     * Rebuild cache for first PREBUILT_PAGES pages across all combinations.
     * Called after rating recalculation.
     */
    public static function rebuildCache(): void
    {
        $controller = new self();
        $physicsList = ['vq3', 'cpm'];
        $gametypes = ['run', 'ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'];
        $rankingtypes = ['active_players', 'all_players'];
        $categories = ['overall', 'strafe', 'slick', 'tele', 'rocket', 'plasma', 'grenade', 'lg', 'bfg'];

        foreach ($physicsList as $physics) {
            foreach ($gametypes as $gametype) {
                foreach ($rankingtypes as $rankingtype) {
                    foreach ($categories as $category) {
                        for ($page = 1; $page <= self::PREBUILT_PAGES; $page++) {
                            $cacheKey = "ranking:{$physics}:{$gametype}:{$rankingtype}:{$category}:{$page}";
                            $data = $controller->fetchPageFromDb($physics, $gametype, $rankingtype, $category, $page);
                            Cache::put($cacheKey, $data, self::CACHE_TTL);
                        }
                    }
                }
            }
        }

        // Refresh recalculation timestamp
        Cache::forget('ranking:last_recalculation');
        Cache::remember('ranking:last_recalculation', self::CACHE_TTL_RECALCULATION, function () {
            return DB::table('player_ratings')->max('updated_at');
        });

        \Log::info('Ranking cache rebuilt (' . self::PREBUILT_PAGES . ' pages per combination)');
    }

    /**
     * Clear all ranking caches. Called before rebuild.
     */
    public static function clearCache(): void
    {
        Cache::forget('ranking:last_recalculation');

        $physicsList = ['vq3', 'cpm'];
        $gametypes = ['run', 'ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'];
        $rankingtypes = ['active_players', 'all_players'];
        $categories = ['overall', 'strafe', 'slick', 'tele', 'rocket', 'plasma', 'grenade', 'lg', 'bfg'];

        foreach ($physicsList as $physics) {
            foreach ($gametypes as $gametype) {
                foreach ($rankingtypes as $rankingtype) {
                    foreach ($categories as $category) {
                        for ($page = 1; $page <= 10; $page++) {
                            Cache::forget("ranking:{$physics}:{$gametype}:{$rankingtype}:{$category}:{$page}");
                        }
                    }
                }
            }
        }

        \Log::info('Ranking page caches cleared');
    }

    public function howItWorks()
    {
        $rows = DB::table('category_stats')
            ->select('physics', 'mode', 'category', 'median_players', 'ranked_maps', 'updated_at')
            ->get();

        $categoryStats = [];
        $lastUpdated = null;
        foreach ($rows as $row) {
            $categoryStats[$row->mode][$row->category][$row->physics] = [
                'median' => (float) $row->median_players,
                'k'      => max((float) $row->median_players / 2, 1),
                'maps'   => (int) $row->ranked_maps,
            ];
            if (!$lastUpdated || $row->updated_at > $lastUpdated) {
                $lastUpdated = $row->updated_at;
            }
        }

        $ratingSettings = collect(RatingSetting::allAsArray())
            ->map(fn ($v) => is_numeric($v) ? (float) $v : $v)
            ->all();

        return Inertia::render('RankingHowItWorks', [
            'categoryStats'      => $categoryStats,
            'categoryStatsAsOf'  => $lastUpdated,
            'ratingSettings'     => $ratingSettings,
        ]);
    }
}
