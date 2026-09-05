<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Maps that play themselves, and so are worth no video.
 *
 * `1strank4free` is the name of the thing: a map built so that everybody who
 * loads it finishes with the same time and takes first place. 86 people hold
 * its 4ms record. `stumpf` has 133 on 8ms. Rendering those is minutes of
 * machine time for a video of nobody doing anything, and putting them in a
 * playlist of fastest runs makes the playlist a joke too.
 *
 * The test is how many people hold the map's best time in one physics. Nothing
 * else was needed: `gvn_jumppad` has a perfectly ordinary looking 10.2 seconds
 * and nineteen people on it, so a time threshold would have let it through
 * while the count catches it.
 *
 * Not `rank`. Ties there are numbered 1, 2, 3 and so on rather than all being
 * 1, so counting rank 1 finds 23 of these maps and misses 72 - stumpf, the
 * worst of the lot, does not appear at all.
 *
 * Nothing is deleted or hidden. The map keeps its page, its records and its
 * demos. It stops getting videos, that is all.
 */
class JokeMaps
{
    private const CACHE_KEY = 'demome:joke_map_pairs';

    /** An hour. The records behind this move slowly and a stale answer for a
     *  few minutes only means one more or one fewer video. */
    private const CACHE_TTL = 3600;

    public static function limit(): int
    {
        return max(2, (int) SiteSetting::get('demome:tied_wr_limit', 10));
    }

    /**
     * Every barred map and physics, as `mapname|physics` in lower case.
     *
     * @return array<string, true> a lookup, not a list
     */
    public static function pairs(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $limit = self::limit();

            // The map's best time per physics, then how many people are on it.
            // Counted by mdd_id, because the same person holding two rows must
            // not read as two people.
            $best = DB::table('records')
                ->whereNull('deleted_at')
                ->selectRaw('mapname, physics, MIN(time) as t')
                ->groupBy('mapname', 'physics');

            $rows = DB::table('records as r')
                ->whereNull('r.deleted_at')
                ->joinSub($best, 'b', fn ($join) => $join
                    ->on('b.mapname', '=', 'r.mapname')
                    ->on('b.physics', '=', 'r.physics')
                    ->on('b.t', '=', 'r.time'))
                ->selectRaw('r.mapname, r.physics')
                ->groupBy('r.mapname', 'r.physics')
                ->havingRaw('COUNT(DISTINCT r.mdd_id) >= ?', [$limit])
                ->get();

            $out = [];

            foreach ($rows as $row) {
                $out[self::key($row->mapname, $row->physics)] = true;
            }

            return $out;
        });
    }

    public static function isJoke(?string $mapName, ?string $physics): bool
    {
        if (! $mapName || ! $physics) {
            return false;
        }

        return isset(self::pairs()[self::key($mapName, $physics)]);
    }

    /**
     * A condition for a raw query, as `(CONCAT(...) NOT IN (...))`.
     *
     * Written as an inline list rather than a subquery on purpose: `records`
     * is over 800 000 rows and grouping it takes seconds, which is not a price
     * the render queue can pay on every poll. The list is around a hundred
     * short strings and is cached.
     *
     * @param  string  $mapColumn  qualified column holding the map name
     * @param  string  $physicsColumn  qualified column holding the physics
     */
    public static function excludeSql(string $mapColumn, string $physicsColumn): string
    {
        $pairs = array_keys(self::pairs());

        if (! $pairs) {
            return '1=1';
        }

        $quoted = implode(',', array_map(fn ($pair) => DB::getPdo()->quote($pair), $pairs));

        return "CONCAT(LOWER({$mapColumn}), '|', LOWER({$physicsColumn})) NOT IN ({$quoted})";
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function key(string $mapName, string $physics): string
    {
        // `CPM` on rendered_videos, `cpm` on records. Same map either way.
        return strtolower(trim($mapName)) . '|' . strtolower(trim($physics));
    }
}
