<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Map;
use App\Models\Record;
use App\Models\MapperClaim;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapperProfileController extends Controller
{
    /**
     * Get the base query for maps claimed by a user
     */
    private function getClaimedMapNames(User $user): array
    {
        $claims = $user->mapperClaims()->where('type', 'map')->with('exclusions')->get();
        $claimNames = $claims->pluck('name')->toArray();

        if (empty($claimNames)) {
            return ['claims' => [], 'mapNames' => collect()];
        }

        // Collect all excluded map IDs across all claims
        $excludedMapIds = $claims->flatMap(fn($c) => $c->exclusions->pluck('map_id'))->unique()->toArray();

        $query = Map::where('visible', true)->where(function ($q) use ($claimNames) {
            foreach ($claimNames as $name) {
                $q->orWhere('author', 'REGEXP', MapperClaim::authorRegexp($name));
            }
        });

        if (!empty($excludedMapIds)) {
            $query->whereNotIn('id', $excludedMapIds);
        }

        return [
            'claims' => $claimNames,
            'mapNames' => $query->pluck('name'),
            'mapQuery' => $query,
        ];
    }

    /**
     * Main stats for the creator tab
     */
    /**
     * The mapper and modeler tabs follow the profile rule: a guest or an
     * unverified login gets the header counts and the public lists (maps,
     * models), not the stats, the top players, the activity or the timeline.
     * Same test as ProfileController::profileLocked().
     */
    private function locked(): bool
    {
        $viewer = auth()->user();

        return ! $viewer || $viewer->email_verified_at === null;
    }

    public function stats(Request $request, $userId)
    {
        $full = $this->fullStats($userId);
        if (! $this->locked() || $full instanceof \Illuminate\Http\JsonResponse || ! is_array($full)) {
            return $full;
        }

        return [
            'has_maps' => $full['has_maps'] ?? false,
            'total_maps' => $full['total_maps'] ?? 0,
            'locked' => true,
        ];
    }

    private function fullStats($userId)
    {
        $user = User::findOrFail($userId);

        return Cache::remember("mapper_stats_{$userId}", 3600, function () use ($user) {
            $data = $this->getClaimedMapNames($user);

            if (empty($data['claims'])) {
                return response()->json(['has_maps' => false]);
            }

            $mapNames = $data['mapNames'];
            $maps = $data['mapQuery']->get();

            $totalMaps = $maps->count();

            if ($totalMaps === 0) {
                return ['has_maps' => false];
            }

            // Total records on claimed maps
            $totalRecords = Record::whereIn('mapname', $mapNames)
                ->whereNull('deleted_at')
                ->count();

            // Unique players
            $uniquePlayers = Record::whereIn('mapname', $mapNames)
                ->whereNull('deleted_at')
                ->distinct('mdd_id')
                ->count('mdd_id');

            // World records count (rank 1)
            $worldRecords = Record::whereIn('mapname', $mapNames)
                ->whereNull('deleted_at')
                ->where('rank', 1)
                ->count();

            // Average records per map
            $avgRecordsPerMap = $totalMaps > 0 ? round($totalRecords / $totalMaps) : 0;

            // Oldest and newest map
            $oldestMap = $maps->sortBy('date_added')->first();
            $newestMap = $maps->sortByDesc('date_added')->first();

            // Physics breakdown
            $physicsBreakdown = [];
            foreach (['vq3', 'cpm'] as $physics) {
                $physicsMaps = $maps->filter(fn ($m) => $m->physics === $physics || $m->physics === 'all');
                $physicsMapNames = $physicsMaps->pluck('name');

                $physicsRecords = Record::whereIn('mapname', $physicsMapNames)
                    ->whereNull('deleted_at')
                    ->where('physics', $physics)
                    ->count();

                $physicsPlayers = Record::whereIn('mapname', $physicsMapNames)
                    ->whereNull('deleted_at')
                    ->where('physics', $physics)
                    ->distinct('mdd_id')
                    ->count('mdd_id');

                $physicsBreakdown[$physics] = [
                    'maps' => $physicsMaps->count(),
                    'records' => $physicsRecords,
                    'unique_players' => $physicsPlayers,
                ];
            }

            // Weapon breakdown with top 3 per physics
            $weaponBreakdown = [];
            $weapons = ['strafe' => '', 'rl' => 'rl', 'gl' => 'gl', 'pg' => 'pg', 'bfg' => 'bfg'];
            foreach ($weapons as $label => $weapon) {
                if ($label === 'strafe') {
                    $weaponMaps = $maps->filter(fn ($m) => !$m->weapons || trim($m->weapons) === '');
                } else {
                    $weaponMaps = $maps->filter(fn ($m) => str_contains($m->weapons ?? '', $weapon));
                }

                $weaponMapNames = $weaponMaps->pluck('name')->toArray();

                if (empty($weaponMapNames)) {
                    continue;
                }

                $physicsData = [];
                foreach (['vq3', 'cpm'] as $physics) {
                    $recCount = Record::whereIn('mapname', $weaponMapNames)
                        ->whereNull('deleted_at')
                        ->where('physics', $physics)
                        ->count();

                    $topPlayers = Record::whereIn('mapname', $weaponMapNames)
                        ->whereNull('deleted_at')
                        ->where('physics', $physics)
                        ->select('mdd_id', DB::raw('MAX(name) as name'), DB::raw('COUNT(*) as record_count'))
                        ->groupBy('mdd_id')
                        ->orderByDesc('record_count')
                        ->limit(3)
                        ->get()
                        ->map(fn ($p) => [
                            'name' => $p->name,
                            'mdd_id' => $p->mdd_id,
                            'record_count' => (int) $p->record_count,
                        ]);

                    $physicsData[$physics] = [
                        'records' => $recCount,
                        'top_players' => $topPlayers,
                    ];
                }

                $weaponBreakdown[$label] = [
                    'maps' => $weaponMaps->count(),
                    'physics' => $physicsData,
                ];
            }

            // Gametype distribution
            $gametypeDistribution = $maps->groupBy('gametype')
                ->map(fn ($group) => $group->count())
                ->toArray();

            // Claim names for display
            $claimNames = $user->mapperClaims()->where('type', 'map')->pluck('name');

            return [
                'has_maps' => true,
                'claim_names' => $claimNames,
                'total_maps' => $totalMaps,
                'total_records' => $totalRecords,
                'unique_players' => $uniquePlayers,
                'world_records' => $worldRecords,
                'avg_records_per_map' => $avgRecordsPerMap,
                'oldest_map' => $oldestMap ? [
                    'name' => $oldestMap->name,
                    'date_added' => $oldestMap->date_added,
                    'thumbnail' => $oldestMap->thumbnail,
                ] : null,
                'newest_map' => $newestMap ? [
                    'name' => $newestMap->name,
                    'date_added' => $newestMap->date_added,
                    'thumbnail' => $newestMap->thumbnail,
                ] : null,
                'physics_breakdown' => $physicsBreakdown,
                'weapon_breakdown' => $weaponBreakdown,
                'gametype_distribution' => $gametypeDistribution,
            ];
        });
    }

    /**
     * Maps grid with filtering and sorting
     */
    public function maps(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $data = $this->getClaimedMapNames($user);

        if (empty($data['claims'])) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $query = $data['mapQuery'];

        // Filters
        if ($request->physics && $request->physics !== 'all') {
            $query->where(function ($q) use ($request) {
                $q->where('physics', $request->physics)
                  ->orWhere('physics', 'all');
            });
        }

        if ($request->gametype && $request->gametype !== 'all') {
            $query->where('gametype', $request->gametype);
        }

        if ($request->weapon && $request->weapon !== 'all') {
            if ($request->weapon === 'strafe') {
                $query->where(function ($q) {
                    $q->whereNull('weapons')->orWhere('weapons', '');
                });
            } else {
                $query->where('weapons', 'LIKE', '%' . $request->weapon . '%');
            }
        }

        if ($request->search) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        // Sorting
        $sort = $request->sort ?? 'newest';
        $mapNames = $query->pluck('name');

        switch ($sort) {
            case 'most_played':
                $query->withCount(['records as player_count' => function ($q) {
                    $q->whereNull('deleted_at')
                      ->select(DB::raw('COUNT(DISTINCT mdd_id)'));
                }])->orderByDesc('player_count');
                break;
            case 'most_records':
                $query->withCount(['records' => function ($q) {
                    $q->whereNull('deleted_at');
                }])->orderByDesc('records_count');
                break;
            case 'oldest':
                $query->orderBy('date_added', 'asc');
                break;
            case 'newest':
            default:
                $query->orderByDesc('date_added');
                break;
        }

        $maps = $query->paginate(12);

        // Enrich with record counts and WR info
        $mapNamesPage = collect($maps->items())->pluck('name');

        $recordCounts = Record::whereIn('mapname', $mapNamesPage)
            ->whereNull('deleted_at')
            ->groupBy('mapname')
            ->select('mapname', DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT mdd_id) as players'))
            ->get()
            ->keyBy(fn ($item) => strtolower($item->mapname));

        // WR holders for each map (rank 1, grouped by physics)
        $wrHolders = Record::whereIn('mapname', $mapNamesPage)
            ->whereNull('deleted_at')
            ->where('rank', 1)
            ->select('mapname', 'name', 'time', 'physics', 'mdd_id')
            ->get()
            ->groupBy(fn ($item) => strtolower($item->mapname));

        $enriched = collect($maps->items())->map(function ($map) use ($recordCounts, $wrHolders) {
            $counts = $recordCounts->get(strtolower($map->name));
            $wrs = $wrHolders->get(strtolower($map->name), collect());

            return [
                'id' => $map->id,
                'name' => $map->name,
                'author' => $map->author,
                'thumbnail' => $map->thumbnail,
                'physics' => $map->physics,
                'gametype' => $map->gametype,
                'weapons' => $map->weapons,
                'functions' => $map->functions,
                'date_added' => $map->date_added,
                'record_count' => $counts ? $counts->total : 0,
                'player_count' => $counts ? $counts->players : 0,
                'world_records' => $wrs->map(fn ($wr) => [
                    'physics' => $wr->physics,
                    'name' => $wr->name,
                    'time' => $wr->time,
                    'mdd_id' => $wr->mdd_id,
                ])->values(),
            ];
        });

        return [
            'data' => $enriched,
            'current_page' => $maps->currentPage(),
            'last_page' => $maps->lastPage(),
            'total' => $maps->total(),
        ];
    }

    /**
     * Top players on this mapper's maps
     */
    public function topPlayers(Request $request, $userId)
    {
        abort_if($this->locked(), 403, 'Verified accounts only.');
        $user = User::findOrFail($userId);

        return Cache::remember("mapper_top_players_{$userId}", 3600, function () use ($user) {
            $data = $this->getClaimedMapNames($user);

            if (empty($data['claims'])) {
                return ['players' => [], 'completionists' => [], 'near_completionists' => [], 'total_maps' => 0];
            }

            $mapNames = $data['mapNames']->toArray();
            $totalMaps = count($mapNames);

            // Top 20 players by record count
            $topPlayers = Record::whereIn('mapname', $mapNames)
                ->whereNull('deleted_at')
                ->select(
                    'mdd_id',
                    DB::raw('MAX(name) as name'),
                    DB::raw('MAX(country) as country'),
                    DB::raw('COUNT(*) as record_count'),
                    DB::raw('SUM(CASE WHEN `rank` = 1 THEN 1 ELSE 0 END) as wr_count'),
                    DB::raw('COUNT(DISTINCT mapname) as maps_played'),
                    DB::raw('ROUND(AVG(`rank`), 1) as avg_rank')
                )
                ->groupBy('mdd_id')
                ->orderByDesc('record_count')
                ->limit(20)
                ->get()
                ->map(function ($player) use ($totalMaps, $mapNames) {
                    // Find favorite map on THIS mapper's maps
                    $favoriteMap = Record::where('mdd_id', $player->mdd_id)
                        ->whereIn('mapname', $mapNames)
                        ->whereNull('deleted_at')
                        ->select('mapname', DB::raw('COUNT(*) as cnt'))
                        ->groupBy('mapname')
                        ->orderByDesc('cnt')
                        ->first();

                    return [
                        'mdd_id' => $player->mdd_id,
                        'name' => $player->name,
                        'country' => $player->country,
                        'record_count' => (int) $player->record_count,
                        'wr_count' => (int) $player->wr_count,
                        'maps_played' => (int) $player->maps_played,
                        'map_coverage' => $totalMaps > 0 ? round(($player->maps_played / $totalMaps) * 100, 1) : 0,
                        'avg_rank' => $player->avg_rank,
                        'favorite_map' => $favoriteMap ? $favoriteMap->mapname : null,
                    ];
                });

            // Completionists per physics
            $completionistsData = [];
            foreach (['vq3', 'cpm'] as $physics) {
                $completionists = Record::whereIn('mapname', $mapNames)
                    ->whereNull('deleted_at')
                    ->where('physics', $physics)
                    ->select(
                        'mdd_id',
                        DB::raw('MAX(name) as name'),
                        DB::raw('MAX(country) as country'),
                        DB::raw('COUNT(DISTINCT mapname) as maps_played'),
                        DB::raw('COUNT(*) as total_records'),
                        DB::raw('ROUND(AVG(`rank`), 1) as avg_rank'),
                        DB::raw('MIN(`rank`) as best_rank')
                    )
                    ->groupBy('mdd_id')
                    ->having('maps_played', '>=', $totalMaps)
                    ->orderByDesc('total_records')
                    ->limit(10)
                    ->get();

                // Always show near-completionists (exclude those who are 100%)
                $completionistMddIds = $completionists->pluck('mdd_id')->toArray();
                $nearCompletionists = Record::whereIn('mapname', $mapNames)
                    ->whereNull('deleted_at')
                    ->where('physics', $physics)
                    ->whereNotIn('mdd_id', $completionistMddIds)
                    ->select(
                        'mdd_id',
                        DB::raw('MAX(name) as name'),
                        DB::raw('MAX(country) as country'),
                        DB::raw('COUNT(DISTINCT mapname) as maps_played'),
                        DB::raw('COUNT(*) as total_records'),
                        DB::raw('ROUND(AVG(`rank`), 1) as avg_rank')
                    )
                    ->groupBy('mdd_id')
                    ->orderByDesc('maps_played')
                    ->limit(5)
                    ->get()
                    ->map(function ($p) use ($totalMaps) {
                        $p->coverage = $totalMaps > 0 ? round(($p->maps_played / $totalMaps) * 100, 1) : 0;
                        return $p;
                    });

                $completionistsData[$physics] = [
                    'completionists' => $completionists,
                    'near_completionists' => $nearCompletionists,
                ];
            }

            return [
                'players' => $topPlayers,
                'completionists_by_physics' => $completionistsData,
                'total_maps' => $totalMaps,
            ];
        });
    }

    /**
     * Recent activity on mapper's maps
     */
    public function recentActivity(Request $request, $userId)
    {
        abort_if($this->locked(), 403, 'Verified accounts only.');
        $user = User::findOrFail($userId);
        $data = $this->getClaimedMapNames($user);

        if (empty($data['claims'])) {
            return ['records' => []];
        }

        $records = Record::whereIn('mapname', $data['mapNames'])
            ->whereNull('deleted_at')
            ->orderByDesc('date_set')
            ->limit(20)
            ->select('id', 'name', 'mapname', 'time', 'rank', 'physics', 'mode', 'date_set', 'mdd_id', 'country')
            ->get();

        return ['records' => $records];
    }

    /**
     * Creation timeline data (maps + models per year)
     */
    public function heatmap(Request $request, $userId)
    {
        abort_if($this->locked(), 403, 'Verified accounts only.');
        $user = User::findOrFail($userId);

        return Cache::remember("mapper_heatmap_{$userId}_v2", 3600, function () use ($user) {
            $data = $this->getClaimedMapNames($user);

            // Maps per year
            $mapYearly = [];
            if (!empty($data['claims'])) {
                $maps = $data['mapQuery']
                    ->whereNotNull('date_added')
                    ->select('name', 'date_added')
                    ->get();

                $mapYearly = $maps->groupBy(function ($map) {
                    return substr($map->date_added, 0, 4);
                })->map(fn ($group) => $group->count())->toArray();
            }

            // Models per year
            $modelYearly = [];
            $modelClaims = $user->mapperClaims()->where('type', 'model')->pluck('name')->toArray();
            if (!empty($modelClaims)) {
                $models = \App\Models\PlayerModel::where('approval_status', 'approved')
                    ->where(function ($q) use ($modelClaims) {
                        foreach ($modelClaims as $name) {
                            $q->orWhere('author', 'REGEXP', MapperClaim::authorRegexp($name));
                        }
                    })
                    ->select('name', 'created_at')
                    ->get();

                $modelYearly = $models->groupBy(function ($m) {
                    return $m->created_at->format('Y');
                })->map(fn ($group) => $group->count())->toArray();
            }

            // Merge all years
            $allYears = array_unique(array_merge(array_keys($mapYearly), array_keys($modelYearly)));
            rsort($allYears);

            $yearly = [];
            foreach ($allYears as $year) {
                $yearly[$year] = [
                    'maps' => $mapYearly[$year] ?? 0,
                    'models' => $modelYearly[$year] ?? 0,
                ];
            }

            return [
                'yearly' => $yearly,
            ];
        });
    }

    /**
     * Highlighted/most popular maps - one per physics
     */
    public function highlightedMap(Request $request, $userId)
    {
        abort_if($this->locked(), 403, 'Verified accounts only.');
        $user = User::findOrFail($userId);

        return Cache::remember("mapper_highlighted_{$userId}_v2", 3600, function () use ($user) {
            $data = $this->getClaimedMapNames($user);

            if (empty($data['claims'])) {
                return ['vq3' => null, 'cpm' => null];
            }

            $mapNames = $data['mapNames']->toArray();
            $result = [];

            foreach (['vq3', 'cpm'] as $physics) {
                $mostPopular = Record::whereIn('mapname', $mapNames)
                    ->whereNull('deleted_at')
                    ->where('physics', $physics)
                    ->select('mapname', DB::raw('COUNT(DISTINCT mdd_id) as player_count'), DB::raw('COUNT(*) as record_count'))
                    ->groupBy('mapname')
                    ->orderByDesc('player_count')
                    ->first();

                if (!$mostPopular) {
                    $result[$physics] = null;
                    continue;
                }

                $map = Map::where('name', $mostPopular->mapname)->first();

                if (!$map) {
                    $result[$physics] = null;
                    continue;
                }

                $wr = Record::where('mapname', $map->name)
                    ->whereNull('deleted_at')
                    ->where('physics', $physics)
                    ->where('rank', 1)
                    ->select('name', 'time', 'mdd_id', 'country', 'date_set')
                    ->first();

                $result[$physics] = [
                    'id' => $map->id,
                    'name' => $map->name,
                    'author' => $map->author,
                    'thumbnail' => $map->thumbnail,
                    'physics' => $map->physics,
                    'gametype' => $map->gametype,
                    'weapons' => $map->weapons,
                    'date_added' => $map->date_added,
                    'player_count' => $mostPopular->player_count,
                    'record_count' => $mostPopular->record_count,
                    'wr' => $wr ? [
                        'name' => $wr->name,
                        'time' => $wr->time,
                        'mdd_id' => $wr->mdd_id,
                        'country' => $wr->country,
                    ] : null,
                ];
            }

            return $result;
        });
    }

    /**
     * Models claimed by this creator
     */
    public function models(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $claimNames = $user->mapperClaims()->where('type', 'model')->pluck('name')->toArray();

        if (empty($claimNames)) {
            return ['models' => [], 'total' => 0, 'total_downloads' => 0, 'total_views' => 0, 'highlighted' => null, 'timeline' => []];
        }

        $query = \App\Models\PlayerModel::where('approval_status', 'approved')
            ->where(function ($q) use ($claimNames) {
                foreach ($claimNames as $name) {
                    $q->orWhere('author', 'REGEXP', MapperClaim::authorRegexp($name));
                }
            });

        $total = $query->count();
        $totalDownloads = (clone $query)->sum('downloads');
        $totalViews = (clone $query)->sum('views');

        $models = $query->orderByDesc('downloads')
            ->get(['id', 'name', 'base_model', 'base_model_file_path', 'model_type', 'author', 'file_path', 'thumbnail', 'idle_gif', 'rotate_gif', 'downloads', 'views', 'category', 'main_file', 'available_skins', 'created_at']);

        // Highlighted: most downloaded model
        $highlighted = $models->first();

        // Timeline: models per year
        $timeline = $models->groupBy(fn ($m) => $m->created_at?->format('Y'))
            ->filter(fn ($group, $year) => $year !== null)
            ->map(fn ($group) => $group->count())
            ->sortKeysDesc()
            ->toArray();

        // Pinned models for this user (or auto-select top 2 by downloads)
        $pinnedIds = $user->pinned_models ?? [];
        $pinnedSelect = ['id', 'name', 'category', 'file_path', 'main_file', 'base_model', 'base_model_file_path', 'model_type', 'thumbnail', 'idle_gif', 'rotate_gif', 'downloads', 'views', 'available_skins'];

        if (!empty($pinnedIds)) {
            $pinnedModels = \App\Models\PlayerModel::whereIn('id', $pinnedIds)
                ->where('approval_status', 'approved')
                ->get($pinnedSelect)
                ->sortBy(fn ($m) => array_search($m->id, $pinnedIds))
                ->values();
        } else {
            // Auto-select top 2 most downloaded
            $pinnedModels = $models->take(2)->map(fn ($m) => $m)->values();
        }

        // Resolve base_model_file_path for skin/mixed packs missing it (same fallback as ModelsController)
        foreach ($pinnedModels as $pinned) {
            if (!$pinned->base_model_file_path && $pinned->model_type !== 'complete' && $pinned->base_model) {
                $baseModel = \App\Models\PlayerModel::whereRaw('LOWER(base_model) = ?', [strtolower($pinned->base_model)])
                    ->where('model_type', 'complete')
                    ->first(['file_path']);
                if ($baseModel) {
                    $pinned->base_model_file_path = $baseModel->file_path;
                }
            }
        }

        $locked = $this->locked();

        return [
            'models' => $models,
            'total' => $total,
            'total_downloads' => $locked ? null : $totalDownloads,
            'total_views' => $locked ? null : $totalViews,
            'highlighted' => $locked ? null : $highlighted,
            'timeline' => $locked ? [] : $timeline,
            'pinned' => $pinnedModels,
            'group_order' => $user->model_group_order,
            'locked' => $locked,
        ];
    }

    /**
     * Save pinned models for a user
     */
    public function savePinnedModels(Request $request)
    {
        $request->validate([
            'pinned_models' => 'array|max:2',
            'pinned_models.*' => 'integer|exists:models,id',
        ]);

        $user = $request->user();
        $user->update(['pinned_models' => $request->pinned_models]);

        return response()->json(['success' => true]);
    }

    /**
     * Save model group display order
     */
    public function saveModelGroupOrder(Request $request)
    {
        $request->validate([
            'model_group_order' => 'array',
            'model_group_order.*' => 'string',
        ]);

        $user = $request->user();
        $user->update(['model_group_order' => $request->model_group_order]);

        return response()->json(['success' => true]);
    }
}
