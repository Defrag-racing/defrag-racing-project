<?php

namespace App\Services;

use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use App\Services\JokeMaps;
use Illuminate\Support\Facades\DB;

class RenderQueueService
{
    // Quality tiers
    const TIER_ONLINE_WR = 1;
    const TIER_OFFLINE_FASTER_WR = 2;
    const TIER_ONLINE_TOP10 = 3;
    const TIER_OFFLINE_WITHIN_10 = 4;
    const TIER_ONLINE_RANK11 = 5;
    const TIER_OFFLINE_WITHIN_50 = 6;
    const TIER_LONGER = 7;
    const TIER_VERY_LONG = 8;

    const TIER_LABELS = [
        1 => 'Online WR',
        2 => 'Offline ≤ WR',
        3 => 'Online Top 2-10',
        4 => 'Offline ≤ 10%',
        5 => 'Online Rank 11+',
        6 => 'Offline ≤ 50%',
        7 => 'Longer (10-50min)',
        8 => 'Very long (50min+)',
    ];

    // Rotation pattern for short demos only (longer/very_long are injected daily)
    const ROTATION = [
        self::TIER_ONLINE_WR,         // 1
        self::TIER_OFFLINE_FASTER_WR, // 2
        self::TIER_OFFLINE_FASTER_WR, // 3
        self::TIER_ONLINE_TOP10,      // 4
        self::TIER_ONLINE_TOP10,      // 5
        self::TIER_OFFLINE_WITHIN_10, // 6
        self::TIER_OFFLINE_WITHIN_10, // 7
        self::TIER_ONLINE_RANK11,     // 8
        self::TIER_OFFLINE_WITHIN_50, // 9
        self::TIER_OFFLINE_WITHIN_50, // 10
    ];

    // Daily limits for long demos
    const DAILY_LONGER_LIMIT = 3;     // 10-50min demos per day
    const DAILY_VERY_LONG_LIMIT = 1;  // 50min+ demos per day

    // Publish rotation (short demos only, long demos publish naturally)
    const PUBLISH_ROTATION = [
        self::TIER_ONLINE_WR,         // 1
        self::TIER_OFFLINE_FASTER_WR, // 2
        self::TIER_OFFLINE_FASTER_WR, // 3
        self::TIER_ONLINE_TOP10,      // 4
        self::TIER_ONLINE_TOP10,      // 5
        self::TIER_OFFLINE_WITHIN_10, // 6
        self::TIER_OFFLINE_WITHIN_10, // 7
        self::TIER_ONLINE_RANK11,     // 8
        self::TIER_OFFLINE_WITHIN_50, // 9
        self::TIER_OFFLINE_WITHIN_50, // 10
        self::TIER_LONGER,            // 11
        self::TIER_VERY_LONG,         // 12
    ];

    /**
     * Determine the quality tier for a demo.
     */
    public static function detectTier(UploadedDemo $demo): int
    {
        // Online (has record_id) - check rank first, time-based tiers only for non-top demos
        if ($demo->record_id) {
            $record = $demo->record;
            if (!$record || $record->deleted_at) {
                return self::TIER_ONLINE_RANK11;
            }

            if ($record->rank === 1) return self::TIER_ONLINE_WR;
            if ($record->rank <= 10) return self::TIER_ONLINE_TOP10;
            return self::TIER_ONLINE_RANK11;
        }

        // Offline - compare with WR on that map+physics
        if ($demo->time_ms && $demo->time_ms > 0 && $demo->map_name && $demo->physics) {
            $wrTime = DB::table('records')
                ->where('mapname', $demo->map_name)
                ->where('physics', $demo->physics)
                ->where('rank', 1)
                ->whereNull('deleted_at')
                ->value('time');

            if ($wrTime && $wrTime > 0) {
                $ratio = $demo->time_ms / $wrTime;
                if ($ratio <= 1.0) return self::TIER_OFFLINE_FASTER_WR;
                if ($ratio <= 1.1) return self::TIER_OFFLINE_WITHIN_10;
                if ($ratio <= 1.5) return self::TIER_OFFLINE_WITHIN_50;
            }
        }

        // Long demos that didn't match any quality tier above
        if ($demo->time_ms && $demo->time_ms > 3000000) {
            return self::TIER_VERY_LONG;
        }
        if ($demo->time_ms && $demo->time_ms >= 600000) {
            return self::TIER_LONGER;
        }

        return self::TIER_OFFLINE_WITHIN_50; // default for unclassifiable
    }

    /**
     * Auto-render eligibility gate.
     *
     * All three queue rules (representative / not time-history, rank in the
     * better half of the field, at most the 3 oldest of an identical time)
     * are precomputed per demo into demos_top_ranks.auto_render_eligible by
     * DemosTopRankService - rebuilt from the SAME Demos Top clustering the web
     * shows. The queue just checks the flag, which also enforces one demo per
     * main record (only that record's single renderable demo gets a row).
     *
     * $d is the uploaded_demos table alias in the surrounding query.
     */
    private static function autoEligibleSql(string $d): string
    {
        // A map that plays itself is worth no video whatever the demo says
        // about it, so the bar sits here beside the queue rules rather than
        // being remembered at each of the seven places that make a row.
        return "EXISTS (SELECT 1 FROM demos_top_ranks dtr"
            . " WHERE dtr.uploaded_demo_id = {$d}.id"
            . " AND dtr.auto_render_eligible = 1)"
            . " AND " . JokeMaps::excludeSql("{$d}.map_name", "{$d}.physics");
    }

    /**
     * Get candidate demos for a specific tier (not yet in rendered_videos).
     */
    public static function getCandidatesForTier(int $tier, int $limit = 5): \Illuminate\Support\Collection
    {
        $query = UploadedDemo::query()
            ->whereDoesntHave('renderedVideo')
            ->where('time_ms', '>', 0)
            ->whereNotNull('map_name')
            ->whereRaw(self::autoEligibleSql('uploaded_demos'));

        switch ($tier) {
            case self::TIER_ONLINE_WR:
                $query->whereNotNull('record_id')
                    ->whereHas('record', fn($q) => $q->where('rank', 1)->whereNull('deleted_at'))
                    ->orderBy('time_ms', 'asc');
                break;

            case self::TIER_OFFLINE_FASTER_WR:
                $ids = DB::select("
                    SELECT d.id FROM uploaded_demos d
                    JOIN records r ON r.mapname = d.map_name AND r.physics = d.physics AND r.rank = 1 AND r.deleted_at IS NULL
                    WHERE d.record_id IS NULL AND d.time_ms > 0 AND d.time_ms <= r.time
                    AND NOT EXISTS (SELECT 1 FROM rendered_videos rv WHERE rv.demo_id = d.id)
                    AND " . self::autoEligibleSql('d') . "
                    ORDER BY d.time_ms ASC LIMIT ?
                ", [$limit]);
                return UploadedDemo::whereIn('id', collect($ids)->pluck('id'))->with('record')->get();

            case self::TIER_ONLINE_TOP10:
                $query->whereNotNull('record_id')
                    ->whereHas('record', fn($q) => $q->where('rank', '>', 1)->where('rank', '<=', 10)->whereNull('deleted_at'))
                    ->orderByRaw('(SELECT r.rank FROM records r WHERE r.id = uploaded_demos.record_id) ASC')
                    ->orderBy('time_ms', 'asc');
                break;

            case self::TIER_OFFLINE_WITHIN_10:
                $ids = DB::select("
                    SELECT d.id FROM uploaded_demos d
                    JOIN records r ON r.mapname = d.map_name AND r.physics = d.physics AND r.rank = 1 AND r.deleted_at IS NULL
                    WHERE d.record_id IS NULL AND d.time_ms > 0 AND d.time_ms > r.time AND d.time_ms <= r.time * 1.1
                    AND NOT EXISTS (SELECT 1 FROM rendered_videos rv WHERE rv.demo_id = d.id)
                    AND " . self::autoEligibleSql('d') . "
                    ORDER BY (d.time_ms / r.time) ASC LIMIT ?
                ", [$limit]);
                return UploadedDemo::whereIn('id', collect($ids)->pluck('id'))->with('record')->get();

            case self::TIER_ONLINE_RANK11:
                $query->whereNotNull('record_id')
                    ->whereHas('record', fn($q) => $q->where('rank', '>', 10)->whereNull('deleted_at'))
                    ->orderByRaw('(SELECT r.rank FROM records r WHERE r.id = uploaded_demos.record_id) ASC')
                    ->orderBy('time_ms', 'asc');
                break;

            case self::TIER_OFFLINE_WITHIN_50:
                $ids = DB::select("
                    SELECT d.id FROM uploaded_demos d
                    JOIN records r ON r.mapname = d.map_name AND r.physics = d.physics AND r.rank = 1 AND r.deleted_at IS NULL
                    WHERE d.record_id IS NULL AND d.time_ms > 0 AND d.time_ms > r.time * 1.1 AND d.time_ms <= r.time * 1.5
                    AND NOT EXISTS (SELECT 1 FROM rendered_videos rv WHERE rv.demo_id = d.id)
                    AND " . self::autoEligibleSql('d') . "
                    ORDER BY (d.time_ms / r.time) ASC LIMIT ?
                ", [$limit]);
                return UploadedDemo::whereIn('id', collect($ids)->pluck('id'))->with('record')->get();

            case self::TIER_LONGER:
                // Exclude demos that would qualify for higher tiers (online WR/top10)
                $query->where('time_ms', '>=', 600000)
                    ->where('time_ms', '<=', 3000000)
                    ->where(function ($q) {
                        $q->whereNull('record_id')
                          ->orWhereHas('record', fn($r) => $r->where('rank', '>', 10)->whereNull('deleted_at'));
                    })
                    ->orderBy('time_ms', 'asc');
                break;

            case self::TIER_VERY_LONG:
                $query->where('time_ms', '>', 3000000)
                    ->where(function ($q) {
                        $q->whereNull('record_id')
                          ->orWhereHas('record', fn($r) => $r->where('rank', '>', 10)->whereNull('deleted_at'));
                    })
                    ->orderBy('time_ms', 'asc');
                break;
        }

        return $query->with('record')->limit($limit)->get();
    }

    /**
     * Count how many longer/very_long demos were queued today.
     */
    private static function getLongDemoCounts(): array
    {
        $today = now()->startOfDay();

        $counts = RenderedVideo::where('created_at', '>=', $today)
            ->whereIn('quality_tier', [self::TIER_LONGER, self::TIER_VERY_LONG])
            ->selectRaw('quality_tier, COUNT(*) as cnt')
            ->groupBy('quality_tier')
            ->pluck('cnt', 'quality_tier');

        return [
            'longer' => $counts->get(self::TIER_LONGER, 0),
            'very_long' => $counts->get(self::TIER_VERY_LONG, 0),
        ];
    }

    /**
     * Get the next N items for the render queue using the rotation pattern.
     * Short demos follow the rotation, long demos are injected based on daily limits.
     */
    public static function getNextBatch(int $count = 5): array
    {
        // Track where we are in the rotation (persisted in site_settings)
        $rotationIndex = (int) (\App\Models\SiteSetting::get('demome:rotation_index', 0));
        $items = [];
        $usedMaps = [];
        $usedDemoIds = [];
        $attempts = 0;
        $maxAttempts = count(self::ROTATION) * 3;

        // Also check recently pending items to avoid same map back-to-back across batches
        $recentMaps = RenderedVideo::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->pluck('map_name')
            ->toArray();
        $usedMaps = array_flip($recentMaps);

        // Check daily long demo counts and inject if under limit
        $longCounts = self::getLongDemoCounts();

        // Pending long demos also count (already queued but not yet completed)
        $pendingLong = RenderedVideo::where('status', 'pending')
            ->whereIn('quality_tier', [self::TIER_LONGER, self::TIER_VERY_LONG])
            ->selectRaw('quality_tier, COUNT(*) as cnt')
            ->groupBy('quality_tier')
            ->pluck('cnt', 'quality_tier');

        $longerToday = $longCounts['longer'] + $pendingLong->get(self::TIER_LONGER, 0);
        $veryLongToday = $longCounts['very_long'] + $pendingLong->get(self::TIER_VERY_LONG, 0);

        // Inject long demos first if daily quota not met
        foreach ([
            [self::TIER_VERY_LONG, $veryLongToday, self::DAILY_VERY_LONG_LIMIT],
            [self::TIER_LONGER, $longerToday, self::DAILY_LONGER_LIMIT],
        ] as [$tier, $current, $limit]) {
            $needed = $limit - $current;
            if ($needed > 0 && count($items) < $count) {
                $candidates = self::getCandidatesForTier($tier, $needed + 5);
                foreach ($candidates as $demo) {
                    if ($needed <= 0 || count($items) >= $count) break;
                    if (!isset($usedMaps[$demo->map_name]) && !isset($usedDemoIds[$demo->id])) {
                        $items[] = ['demo' => $demo, 'tier' => $tier];
                        $usedMaps[$demo->map_name] = true;
                        $usedDemoIds[$demo->id] = true;
                        $needed--;
                    }
                }
            }
        }

        // Fill remaining slots with short demo rotation
        while (count($items) < $count && $attempts < $maxAttempts) {
            $tier = self::ROTATION[$rotationIndex % count(self::ROTATION)];
            $candidates = self::getCandidatesForTier($tier, 10);

            $picked = null;
            foreach ($candidates as $demo) {
                if (!isset($usedMaps[$demo->map_name]) && !isset($usedDemoIds[$demo->id])) {
                    $picked = $demo;
                    break;
                }
            }

            if ($picked) {
                $items[] = [
                    'demo' => $picked,
                    'tier' => $tier,
                ];
                $usedMaps[$picked->map_name] = true;
                $usedDemoIds[$picked->id] = true;
            }

            $rotationIndex++;
            $attempts++;
        }

        \App\Models\SiteSetting::set('demome:rotation_index', $rotationIndex % count(self::ROTATION));

        return $items;
    }

    /**
     * Get next videos to auto-publish using the publish rotation pattern.
     */
    public static function getNextPublishBatch(int $count): \Illuminate\Support\Collection
    {
        $publishIndex = (int) (\App\Models\SiteSetting::get('demome:publish_rotation_index', 0));
        $items = collect();
        $usedMaps = [];
        $attempts = 0;
        $maxAttempts = count(self::PUBLISH_ROTATION) * 3;

        // Same field-relative pace gate as the render eligibility: don't publish
        // an already-rendered run that's more than `max_wr_ratio` times slower
        // than the map WR (off the field's level). Cast float, safe to inline.
        $maxRatio = (float) \App\Models\SiteSetting::get('demome:max_wr_ratio', 2.0);
        $paceGate = $maxRatio > 0
            ? "(NOT EXISTS (SELECT 1 FROM records r WHERE r.mapname = rendered_videos.map_name AND r.physics = rendered_videos.physics AND r.rank = 1 AND r.deleted_at IS NULL)"
                . " OR EXISTS (SELECT 1 FROM records r WHERE r.mapname = rendered_videos.map_name AND r.physics = rendered_videos.physics AND r.rank = 1 AND r.deleted_at IS NULL AND rendered_videos.time_ms <= r.time * {$maxRatio}))"
            : '1=1';

        // Short runs (< 5s) are kept but deprioritized: only picked when nothing
        // longer is available for the tier.
        $shortThresholdMs = (int) \App\Models\SiteSetting::get('demome:short_demo_ms', 5000);

        // Avoid same map as recently published
        $recentPublishedMaps = RenderedVideo::where('status', 'completed')
            ->whereNotNull('published_at')
            ->orderBy('published_at', 'desc')
            ->limit(10)
            ->pluck('map_name')
            ->toArray();
        $usedMaps = array_flip($recentPublishedMaps);

        // How much of each tier may go out in a day. The rotation already says
        // this - a tier holding two of the twelve positions is meant to be a
        // sixth of the day - but as a sequence it only held for one pass. The
        // loop keeps going until it has $count videos, so with every other tier
        // empty it came back round and filled the whole day from the one tier
        // that had stock: drain the rest and the channel publishes nothing but
        // world records. As a cap the same weights hold on every pass.
        $tierCaps = array_count_values(self::PUBLISH_ROTATION);

        // What already counts toward today, the same pair the daily total uses
        // in autoApprovePublish(): published today, plus approved today and not
        // yet published. Without the second half the bot's four-hourly call
        // would hand out the same tier's whole day again before the first batch
        // had gone public.
        $usedToday = RenderedVideo::where('source', 'auto')
            ->where(function ($q) {
                $q->whereDate('published_at', today())
                    ->orWhere(fn($inner) => $inner->where('publish_approved', true)
                        ->whereNull('published_at')
                        ->whereDate('updated_at', today()));
            })
            ->selectRaw('COALESCE(quality_tier, 0) as tier, COUNT(*) as cnt')
            ->groupByRaw('COALESCE(quality_tier, 0)')
            ->pluck('cnt', 'tier')
            ->toArray();

        while ($items->count() < $count && $attempts < $maxAttempts) {
            $tier = self::PUBLISH_ROTATION[$publishIndex % count(self::PUBLISH_ROTATION)];

            // This tier has had its day. Move on rather than take another.
            if (($usedToday[$tier] ?? 0) >= ($tierCaps[$tier] ?? 0)) {
                $publishIndex++;
                $attempts++;
                continue;
            }

            $excludeMapNames = array_keys($usedMaps);
            $baseQuery = RenderedVideo::where('status', 'completed')
                ->whereNotNull('youtube_video_id')
                ->whereNotNull('youtube_url')
                ->where('is_visible', true)
                ->whereNull('published_at')
                ->where(fn($q) => $q->whereNull('publish_approved')->orWhere('publish_approved', false))
                ->where('source', 'auto')
                ->when(!empty($excludeMapNames), fn($q) => $q->whereNotIn('map_name', $excludeMapNames))
                ->whereRaw($paceGate)
                // Deprioritize short runs, then oldest-first within that.
                ->orderByRaw("CASE WHEN time_ms < {$shortThresholdMs} THEN 1 ELSE 0 END ASC")
                ->orderBy('created_at');

            $video = $baseQuery->where('quality_tier', $tier)->first();

            if ($video) {
                $items->push($video);
                $usedMaps[$video->map_name] = true;
                $usedToday[$tier] = ($usedToday[$tier] ?? 0) + 1;
            }

            $publishIndex++;
            $attempts++;
        }

        \App\Models\SiteSetting::set('demome:publish_rotation_index', $publishIndex % count(self::PUBLISH_ROTATION));

        return $items;
    }

    /**
     * Get pool counts per tier for the admin preview.
     */
    public static function getPoolCounts(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('demome:pool_counts', 1800, function () {
            // Batch query rendered_videos counts per tier in 1 query
            $renderedCounts = RenderedVideo::selectRaw('quality_tier, status, COUNT(*) as cnt, SUM(CASE WHEN published_at IS NULL THEN 1 ELSE 0 END) as unpub')
                ->whereNotNull('quality_tier')
                ->groupBy('quality_tier', 'status')
                ->get()
                ->groupBy('quality_tier');

            $counts = [];
            foreach (self::TIER_LABELS as $tier => $label) {
                $tierData = $renderedCounts->get($tier, collect());
                $completed = $tierData->firstWhere('status', 'completed');
                $pending = $tierData->firstWhere('status', 'pending');

                $counts[$tier] = [
                    'label' => $label,
                    'available' => self::countTierPool($tier),
                    'rendered' => $completed ? (int) $completed->cnt : 0,
                    'pending' => $pending ? (int) $pending->cnt : 0,
                    'unpublished' => $completed ? (int) $completed->unpub : 0,
                ];
            }
            return $counts;
        });
    }

    private static function countTierPool(int $tier): int
    {
        return \Illuminate\Support\Facades\Cache::remember("tier_pool_count_{$tier}", 3600, function () use ($tier) {
            // The auto-render eligibility gate (rank-half / identical-time /
            // representative rules) lives in demos_top_ranks, so the pool count
            // applies it once here in $base for every tier.
            $base = "NOT EXISTS (SELECT 1 FROM rendered_videos rv WHERE rv.demo_id = d.id) AND d.time_ms > 0 AND d.map_name IS NOT NULL AND " . self::autoEligibleSql('d');

            $sql = match ($tier) {
                self::TIER_ONLINE_WR => "SELECT COUNT(*) as c FROM uploaded_demos d JOIN records r ON r.id = d.record_id WHERE r.rank = 1 AND r.deleted_at IS NULL AND {$base}",
                self::TIER_OFFLINE_FASTER_WR => "SELECT COUNT(*) as c FROM uploaded_demos d JOIN records r ON r.mapname = d.map_name AND r.physics = d.physics AND r.rank = 1 AND r.deleted_at IS NULL WHERE d.record_id IS NULL AND d.time_ms <= r.time AND {$base}",
                self::TIER_ONLINE_TOP10 => "SELECT COUNT(*) as c FROM uploaded_demos d JOIN records r ON r.id = d.record_id WHERE r.rank > 1 AND r.rank <= 10 AND r.deleted_at IS NULL AND {$base}",
                self::TIER_OFFLINE_WITHIN_10 => "SELECT COUNT(*) as c FROM uploaded_demos d JOIN records r ON r.mapname = d.map_name AND r.physics = d.physics AND r.rank = 1 AND r.deleted_at IS NULL WHERE d.record_id IS NULL AND d.time_ms > r.time AND d.time_ms <= r.time * 1.1 AND {$base}",
                self::TIER_ONLINE_RANK11 => "SELECT COUNT(*) as c FROM uploaded_demos d JOIN records r ON r.id = d.record_id WHERE r.rank > 10 AND r.deleted_at IS NULL AND {$base}",
                self::TIER_OFFLINE_WITHIN_50 => "SELECT COUNT(*) as c FROM uploaded_demos d JOIN records r ON r.mapname = d.map_name AND r.physics = d.physics AND r.rank = 1 AND r.deleted_at IS NULL WHERE d.record_id IS NULL AND d.time_ms > r.time * 1.1 AND d.time_ms <= r.time * 1.5 AND {$base}",
                self::TIER_LONGER => "SELECT COUNT(*) as c FROM uploaded_demos d WHERE d.time_ms >= 600000 AND d.time_ms <= 3000000 AND {$base}",
                self::TIER_VERY_LONG => "SELECT COUNT(*) as c FROM uploaded_demos d WHERE d.time_ms > 3000000 AND {$base}",
                default => "SELECT 0 as c",
            };

            return DB::select($sql)[0]->c;
        });
    }

    /**
     * Preview: get candidates per tier for admin display.
     */
    public static function getPreview(int $perTier = 10): array
    {
        $preview = [];
        foreach (self::TIER_LABELS as $tier => $label) {
            $candidates = self::getCandidatesForTier($tier, $perTier);
            $preview[$tier] = [
                'label' => $label,
                'items' => $candidates,
            ];
        }
        return $preview;
    }
}
