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
 * Two tests, and either is enough.
 *
 * Three or more people on the same record time in one physics. Times step in
 * 8ms at 125fps, so a tie is not impossible on a short map, but three people
 * landing on the same one is already the mark of a map with a single fixed
 * outcome rather than a run.
 *
 * Or two people, when the time is under a second. Nobody runs anything in
 * under a second; a record like that is the map handing it to you.
 *
 * A time test alone would not do. `run-afk` is 56 minutes with thirty people
 * on it and `gvn_jumppad` 10.2 seconds with nineteen, and both play
 * themselves.
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

    /** Tied players that make a map a joke whatever the time is. */
    public static function limit(): int
    {
        return max(2, (int) SiteSetting::get('demome:tied_wr_limit', 3));
    }

    /** Tied players that make a map a joke when the time is also absurd. */
    public static function shortLimit(): int
    {
        return max(2, (int) SiteSetting::get('demome:tied_wr_short_limit', 2));
    }

    /** The time below which nobody is really running anything, in ms. */
    public static function shortMs(): int
    {
        return max(0, (int) SiteSetting::get('demome:tied_wr_short_ms', 1000));
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
            $shortLimit = self::shortLimit();
            $shortMs = self::shortMs();

            // The map's best time per physics, then how many people are on it.
            // Counted by mdd_id, because the same person holding two rows must
            // not read as two people.
            $best = DB::table('records')
                ->whereNull('deleted_at')
                ->selectRaw('mapname, physics, MIN(time) as t')
                ->groupBy('mapname', 'physics');

            // Either test is enough, and neither covers the other. A handful of
            // people on an eight millisecond record is a map that finishes the
            // moment you spawn. A crowd on an ordinary looking time is a map
            // that runs itself more slowly: `run-afk` is 56 minutes with thirty
            // people on it and `gvn_jumppad` 10.2 seconds with nineteen, so a
            // time test on its own would wave both of those through.
            $rows = DB::table('records as r')
                ->whereNull('r.deleted_at')
                ->joinSub($best, 'b', fn ($join) => $join
                    ->on('b.mapname', '=', 'r.mapname')
                    ->on('b.physics', '=', 'r.physics')
                    ->on('b.t', '=', 'r.time'))
                ->selectRaw('r.mapname, r.physics')
                ->groupBy('r.mapname', 'r.physics', 'r.time')
                ->havingRaw(
                    'COUNT(DISTINCT r.mdd_id) >= ? OR (COUNT(DISTINCT r.mdd_id) >= ? AND r.time < ?)',
                    [$limit, $shortLimit, $shortMs]
                )
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
