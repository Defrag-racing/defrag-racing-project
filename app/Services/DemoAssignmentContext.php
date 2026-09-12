<?php

namespace App\Services;

use App\Models\Record;
use App\Models\UploadedDemo;
use App\Models\UserAlias;
use Illuminate\Support\Collection;

/**
 * Everything somebody needs on screen to answer one question: which record is
 * this demo a recording of?
 *
 * The public Community Tasks page has always assembled this - every record on
 * the map, each one's distance from the demo's time, and whether the demo's
 * player name matches an alias of the record holder. The admin panel, which
 * exists for the demos the public flow could NOT decide, assembled none of it:
 * a table, a modal listing the same records again, and a box to type a record
 * id into. Whoever had to make the call went looking for that id by hand, on
 * the demos that were already the hard ones.
 *
 * So it lives here, and both use it. Copying it into the admin would have
 * worked on the day it was written and drifted from the next change onwards,
 * which is how the two screens came to disagree in the first place: the
 * Filament resource already carried its own copy of `resolveGametype`, with a
 * comment saying so.
 */
class DemoAssignmentContext
{
    /** How many distinct times count as "closest". */
    public const CLOSEST_TIMES = 5;

    /**
     * The full picture for one demo, or null when the map has no records at
     * all - there is nothing to choose from and nothing to show.
     *
     * @return array{records: array, closest: array, gametype: string, alias_matches: array}|null
     */
    public function for(UploadedDemo $demo): ?array
    {
        $gametype = $this->resolveGametype($demo);

        $records = Record::where('mapname', $demo->map_name)
            ->where('gametype', $gametype)
            ->whereNull('deleted_at')
            ->with([
                'user:id,name',
                'uploadedDemos:id,record_id',
                'renderedVideos:id,record_id,youtube_url,youtube_video_id',
            ])
            ->orderBy('time')
            ->limit(100)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $aliasMatches = $this->findAliasMatches(
            (string) $demo->player_name,
            $records->pluck('mdd_id')->filter()->unique()->values()->toArray()
        );

        $time = (int) $demo->time_ms;

        return [
            'gametype' => $gametype,
            'alias_matches' => $aliasMatches,
            'closest' => $this->getClosestMatches($time, $records, self::CLOSEST_TIMES, $aliasMatches),
            'records' => $records->map(fn ($r) => $this->mapRecord($r, $time, $aliasMatches))->values()->toArray(),
        ];
    }

    /**
     * `records.gametype` is a `run_<physics>` string. A demo's own `gametype`
     * is usually a mod string (`mdf`, `df`), so it is only usable when it
     * already carries the record convention.
     */
    public function resolveGametype(UploadedDemo $demo): string
    {
        if ($demo->gametype && str_starts_with($demo->gametype, 'run_')) {
            return $demo->gametype;
        }

        // `VQ3.TR` (a timereset run) is still a run_vq3 record; drop the suffix.
        return 'run_' . preg_replace('/\..*$/', '', strtolower($demo->physics ?? 'vq3'));
    }

    public function stripQ3Colors(string $text): string
    {
        return preg_replace('/\^[0-9]/', '', $text);
    }

    /**
     * Which record holders have ever gone by the name written in this demo.
     *
     * The strongest signal there is, and the one the admin panel was missing
     * entirely: a run's file says who recorded it, and if exactly one player on
     * this map has used that name, the question is usually already answered.
     *
     * `exact` after stripping colour codes and case; `similar` when one
     * contains the other, which is what a clan tag does - `[gt]neiT` and
     * `neiT` are one person.
     *
     * @param  array<int>  $mddIds
     */
    public function findAliasMatches(string $demoPlayerName, array $mddIds): array
    {
        if (empty($mddIds) || trim($demoPlayerName) === '') {
            return [];
        }

        $demoClean = $this->stripQ3Colors(strtolower(trim($demoPlayerName)));

        $aliases = UserAlias::whereIn('mdd_id', $mddIds)
            ->select('mdd_id', 'alias', 'alias_colored')
            ->get();

        $matches = [];

        foreach ($aliases->groupBy('mdd_id') as $mddId => $playerAliases) {
            foreach ($playerAliases as $alias) {
                $aliasClean = strtolower(trim($alias->alias));

                if ($aliasClean === $demoClean) {
                    $matches[$mddId] = [
                        'alias' => $alias->alias,
                        'alias_colored' => $alias->alias_colored,
                        'type' => 'exact',
                    ];
                    break;
                }

                if (! isset($matches[$mddId]) && strlen($aliasClean) >= 2 && strlen($demoClean) >= 2) {
                    if (str_contains($aliasClean, $demoClean) || str_contains($demoClean, $aliasClean)) {
                        $matches[$mddId] = [
                            'alias' => $alias->alias,
                            'alias_colored' => $alias->alias_colored,
                            'type' => 'similar',
                        ];
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * The nearest few runs by time, counted in distinct TIMES rather than in
     * rows: several people share a time on an easy map, and five rows that are
     * all the same time are not five candidates.
     *
     * An alias match that did not make the cut is pushed in at the top anyway.
     * A name that matches is worth looking at however far the time is - the
     * demo may be of a run that was never submitted.
     */
    public function getClosestMatches(int $demoTime, Collection $records, int $uniqueTimeCount, array $aliasMatches = []): array
    {
        $sorted = $records->sortBy(fn ($r) => abs($r->time - $demoTime));

        $uniqueTimes = [];

        foreach ($sorted as $record) {
            $uniqueTimes[$record->time] = true;

            if (count($uniqueTimes) >= $uniqueTimeCount) {
                break;
            }
        }

        $matches = $records->filter(fn ($r) => isset($uniqueTimes[$r->time]))
            ->sortBy(fn ($r) => [abs($r->time - $demoTime), $r->rank])
            ->values()
            ->map(fn ($r) => $this->mapRecord($r, $demoTime, $aliasMatches))
            ->toArray();

        if (! empty($aliasMatches)) {
            $matchedIds = collect($matches)->pluck('id')->toArray();

            $aliasHits = $records->filter(
                fn ($r) => $r->mdd_id && isset($aliasMatches[$r->mdd_id]) && ! in_array($r->id, $matchedIds)
            );

            foreach ($aliasHits as $hit) {
                array_unshift($matches, $this->mapRecord($hit, $demoTime, $aliasMatches));
            }
        }

        return $matches;
    }

    public function mapRecord($r, int $demoTime, array $aliasMatches = []): array
    {
        $demo = $r->uploadedDemos->first();
        $video = $r->renderedVideos->first();

        $result = [
            'id' => $r->id,
            'time' => $r->time,
            'player_name' => $r->user?->name ?? $r->name,
            'rank' => $r->rank,
            'time_diff' => abs($r->time - $demoTime),
            'demo_id' => $demo?->id,
            'youtube_url' => $video?->youtube_url,
        ];

        if ($r->mdd_id && isset($aliasMatches[$r->mdd_id])) {
            $result['matched_alias'] = $aliasMatches[$r->mdd_id]['alias'];
            $result['matched_alias_colored'] = $aliasMatches[$r->mdd_id]['alias_colored'];
            $result['alias_match_type'] = $aliasMatches[$r->mdd_id]['type'];
        }

        return $result;
    }
}
