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
        $country = $this->requestedCountry($request);

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
                ->with('countries', $this->rankedCountries($gametype, $rankingtype, $category))
                ->with('lastRecalculation', $lastRecalculation);
        }

        // Partial reload: serve from cache
        $vq3Page = max(1, (int) $request->input('vq3Page', 1));
        $cpmPage = max(1, (int) $request->input('cpmPage', 1));

        $vq3Ratings = $this->getCachedPage('vq3', $gametype, $rankingtype, $category, $vq3Page, $country);
        $cpmRatings = $this->getCachedPage('cpm', $gametype, $rankingtype, $category, $cpmPage, $country);

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
            ->with('countries', $this->rankedCountries($gametype, $rankingtype, $category))
            ->with('lastRecalculation', $lastRecalculation);
    }

    /**
     * The country asked for, or null for the whole board. Two letters, as
     * the flags are named; anything else is read as no filter at all.
     */
    private function requestedCountry(Request $request): ?string
    {
        $country = strtoupper(trim((string) $request->input('country', '')));

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }

    /**
     * The players of one country: the linked account's country decides,
     * and the q3df profile answers for everybody who never linked one -
     * the same order the flag on the row is picked in, so the list holds
     * exactly the rows wearing that flag.
     */
    private function mddIdsOfCountry(string $country): array
    {
        return Cache::remember("ranking:country_players:{$country}", self::CACHE_TTL, function () use ($country) {
            $linked = DB::table('users')->whereNotNull('mdd_id')->pluck('country', 'mdd_id');

            $profiles = DB::table('mdd_profiles')
                ->whereNotNull('country')
                ->pluck('country', 'id');

            $ids = [];
            foreach ($profiles as $mddId => $profileCountry) {
                $theirs = $linked[$mddId] ?? null;
                $theirs = ($theirs === null || $theirs === '' || $theirs === '_404' || $theirs === 'XX')
                    ? $profileCountry
                    : $theirs;

                if (strtoupper((string) $theirs) === $country) {
                    $ids[] = (int) $mddId;
                }
            }

            // A linked account whose q3df profile the site has not scraped yet.
            foreach ($linked as $mddId => $userCountry) {
                if (strtoupper((string) $userCountry) === $country) {
                    $ids[] = (int) $mddId;
                }
            }

            return array_values(array_unique($ids));
        });
    }

    /**
     * Every country with at least one player in the board as it currently
     * stands, with how many - counted under the same filters the board is
     * under, so the number beside a country is the number of rows picking
     * it leaves. The page sorts them by name, which the code cannot do.
     */
    private function rankedCountries(string $gametype, string $rankingtype, string $category): array
    {
        $generation = Cache::rememberForever('ranking:generation', fn () => 1);

        return Cache::remember("ranking:countries:g{$generation}:{$gametype}:{$rankingtype}:{$category}", self::CACHE_TTL, function () use ($gametype, $rankingtype, $category) {
            $query = DB::table('player_ratings')
                ->leftJoin('users', 'users.mdd_id', '=', 'player_ratings.mdd_id')
                ->leftJoin('mdd_profiles', 'mdd_profiles.id', '=', 'player_ratings.mdd_id')
                ->selectRaw("UPPER(COALESCE(NULLIF(NULLIF(NULLIF(users.country, ''), '_404'), 'XX'), NULLIF(NULLIF(mdd_profiles.country, ''), '_404'))) AS country, COUNT(DISTINCT player_ratings.id) AS players")
                ->whereNull('player_ratings.deleted_at')
                ->where('player_ratings.mode', $gametype)
                ->where('player_ratings.category', $category);

            if ($rankingtype === 'active_players') {
                $query->where('player_ratings.last_activity', '>=', now()->subMonths(self::ACTIVE_PLAYERS_MONTHS))
                      ->where('player_ratings.active_players_rank', '>', 0);
            }

            $rows = $query
                ->groupBy('country')
                // On the alias, not on a column: both joined tables have one
                // called country and a bare name is ambiguous.
                ->havingRaw("country IS NOT NULL AND country != ''")
                ->orderBy('country')
                ->get();

            return $rows
                ->filter(fn ($row) => preg_match('/^[A-Z]{2}$/', (string) $row->country))
                ->map(fn ($row) => ['code' => $row->country, 'players' => (int) $row->players])
                ->values()
                ->all();
        });
    }

    /**
     * Cache keys carry a generation, so clearing the ranking is one counter
     * away instead of a list of every key that could exist. With a country
     * filter the combinations are no longer enumerable.
     */
    public static function pageCacheKey(string $physics, string $gametype, string $rankingtype, string $category, int $page, ?string $country = null): string
    {
        $generation = Cache::rememberForever('ranking:generation', fn () => 1);

        return "ranking:g{$generation}:{$physics}:{$gametype}:{$rankingtype}:{$category}:{$page}" . ($country ? ":{$country}" : '');
    }

    /**
     * Get a cached page. Pages 1-3 are prebuilt (12h cache), pages 4+ are cached on access (12h).
     */
    private function getCachedPage(string $physics, string $gametype, string $rankingtype, string $category, int $page, ?string $country = null): LengthAwarePaginator
    {
        $cacheKey = self::pageCacheKey($physics, $gametype, $rankingtype, $category, $page, $country);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($physics, $gametype, $rankingtype, $category, $page, $country) {
            return $this->fetchPageFromDb($physics, $gametype, $rankingtype, $category, $page, $country);
        });
    }

    /**
     * Fetch a page of ratings from DB.
     */
    public function fetchPageFromDb(string $physics, string $gametype, string $rankingtype, string $category, int $page = 1, ?string $country = null): LengthAwarePaginator
    {
        $query = PlayerRating::query();
        $columnToChange = '';

        if ($country) {
            $query->whereIn('mdd_id', $this->mddIdsOfCountry($country));
        }

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
                            $cacheKey = self::pageCacheKey($physics, $gametype, $rankingtype, $category, $page);
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

        // One step of the generation retires every cached page at once,
        // filtered ones included. The old entries expire on their own.
        $generation = Cache::rememberForever('ranking:generation', fn () => 1);
        Cache::forever('ranking:generation', $generation + 1);

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
