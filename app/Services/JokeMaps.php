<?php

namespace App\Services;

use App\Models\MapRenderOverride;
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
    /** The ordinary defrag record. The rest of the column is fastcap. */
    public const MODE = 'run';

    private const CACHE_KEY = 'demome:joke_map_pairs';

    /** An hour. The records behind this move slowly and a stale answer for a
     *  few minutes only means one more or one fewer video. */
    private const CACHE_TTL = 3600;

    /**
     * Players sharing the record, for the main test. Their time must also be
     * under maxMs().
     */
    public static function limit(): int
    {
        return max(2, (int) SiteSetting::get('demome:tied_wr_limit', 3));
    }

    /** The time the main test will not look past, in ms. */
    public static function maxMs(): int
    {
        return max(0, (int) SiteSetting::get('demome:tied_wr_max_ms', 1000));
    }

    /**
     * Players sharing the record with no time test at all. Off by default.
     *
     * A map with a long record is left alone however many people are on it,
     * which is the whole point of the time: the list is meant to be maps that
     * finish in a moment. `run-afk` hands the same 56 minutes to everyone who
     * loads it and is not caught, and that is on purpose. Raise this above 0
     * only to go after maps of that shape, and expect to check what it takes
     * with it.
     */
    public static function crowdLimit(): int
    {
        return max(0, (int) SiteSetting::get('demome:tied_wr_crowd_limit', 0));
    }

    /**
     * Every barred map and physics, as `mapname|physics` in lower case.
     *
     * @return array<string, true> a lookup, not a list
     */
    public static function pairs(): array
    {
        return array_map(fn () => true, self::detail());
    }

    /**
     * The same, with why: how many players are tied and on what time, and
     * whether a person put it there or took the rule's word for it.
     *
     * @return array<string, array{map: string, physics: string, time: int, players: int, source: string, note: ?string}>
     */
    public static function detail(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $limit = self::limit();
            $maxMs = self::maxMs();
            $crowd = self::crowdLimit();

            // The map's best time per physics, then how many people are on it.
            // Counted by mdd_id, because the same person holding two rows must
            // not read as two people.
            // Every leaderboard on its own: a map's ctf2 record is not its
            // record, and people sharing one say nothing about the others.
            $best = DB::table('records')
                ->whereNull('deleted_at')
                ->selectRaw('mapname, physics, mode, MIN(time) as t')
                ->groupBy('mapname', 'physics', 'mode');

            // Either test is enough. The main one is a count and a time
            // together, so a map with a long record is left alone however many
            // people are on it. The second has no time at all and exists only
            // for the maps that hand the same long time to everyone; its count
            // is set high enough that no real map comes near it, and 0 turns it
            // off completely.
            $rows = DB::table('records as r')
                ->whereNull('r.deleted_at')
                ->joinSub($best, 'b', fn ($join) => $join
                    ->on('b.mapname', '=', 'r.mapname')
                    ->on('b.physics', '=', 'r.physics')
                    ->on('b.mode', '=', 'r.mode')
                    ->on('b.t', '=', 'r.time'))
                ->selectRaw('r.mapname, r.physics, r.mode, r.time, COUNT(DISTINCT r.mdd_id) as players')
                ->groupBy('r.mapname', 'r.physics', 'r.mode', 'r.time')
                ->havingRaw(
                    $crowd > 0
                        ? '(COUNT(DISTINCT r.mdd_id) >= ? AND r.time < ?) OR COUNT(DISTINCT r.mdd_id) >= ?'
                        : '(COUNT(DISTINCT r.mdd_id) >= ? AND r.time < ?) AND ? > 0',
                    [$limit, $maxMs, $crowd > 0 ? $crowd : 1]
                )
                ->get();

            $out = [];

            foreach ($rows as $row) {
                $out[self::key($row->mapname, $row->physics, $row->mode)] = [
                    'map' => $row->mapname,
                    'physics' => $row->physics,
                    'mode' => $row->mode,
                    'time' => (int) $row->time,
                    'players' => (int) $row->players,
                    'source' => 'rule',
                    'note' => null,
                ];
            }

            // A person's decision beats the count, in both directions. The rule
            // reads a number and a number is wrong both ways: a hard map can
            // end up with three people on one time, and a map built to hand out
            // first place can sit just under the bar. Moving the number to fix
            // one map moves every other map with it.
            foreach (MapRenderOverride::all() as $override) {
                $key = self::key($override->map_name, $override->physics, $override->gamemode ?: self::MODE);

                if ($override->mode === MapRenderOverride::ALLOW) {
                    unset($out[$key]);

                    continue;
                }

                $out[$key] = [
                    'map' => $override->map_name,
                    'physics' => $override->physics,
                    'mode' => $override->gamemode ?: self::MODE,
                    'time' => $out[$key]['time'] ?? 0,
                    'players' => $out[$key]['players'] ?? 0,
                    'source' => 'admin',
                    'note' => $override->note,
                ];
            }

            return $out;
        });
    }

    /**
     * Maps the rule caught that a person has let through. Shown in the admin
     * beside the barred ones, or an override becomes invisible the moment it
     * works.
     *
     * @return array<string, array{map: string, physics: string, mode: string, note: ?string}>
     */
    public static function allowed(): array
    {
        $out = [];

        foreach (MapRenderOverride::where('mode', MapRenderOverride::ALLOW)->get() as $override) {
            // The leaderboard belongs in the key, the same as everywhere else.
            // Leaving it out took the third argument off a call that has no
            // default for it, and the page died the moment the first override
            // existed - and only then, because until one does this loop never
            // runs. It also made a key Undo could not split back apart.
            $mode = $override->gamemode ?: self::MODE;

            $out[self::key($override->map_name, $override->physics, $mode)] = [
                'map' => $override->map_name,
                'physics' => $override->physics,
                'mode' => $mode,
                'note' => $override->note,
            ];
        }

        return $out;
    }

    /**
     * A stored physics string split into the physics and the leaderboard it
     * belongs to.
     *
     * `CPM` and `VQ3` are ordinary runs. `CPM.TR` is still an ordinary run,
     * only recorded under the timereset ruleset. A number after the dot is a
     * fastcap, and the number is which CTF mode: `VQ3.2` is vq3 ctf2, which is
     * its own leaderboard with its own record.
     *
     * Getting this wrong is what put `ut_zomg` on the blocked list. Its three
     * CTF modes were read as one, the smallest time across all of them became
     * its "record", and four people who share a ctf2 time were counted as four
     * people sharing the map's record. The site shows its real vq3 record as
     * 0.344s held by one person.
     *
     * @return array{0: string, 1: string}|null physics and mode, or null
     */
    public static function readPhysics(?string $stored): ?array
    {
        $parts = explode('.', strtolower(trim((string) $stored)));
        $physics = $parts[0] ?? '';

        if (! in_array($physics, ['cpm', 'vq3'], true)) {
            return null;
        }

        $suffix = $parts[1] ?? '';

        return [$physics, ctype_digit($suffix) ? 'ctf' . (int) $suffix : self::MODE];
    }

    /**
     * @param  string|null  $physics  as stored, so `CPM`, `VQ3.TR` or `CPM.2`
     */
    public static function isJoke(?string $mapName, ?string $physics): bool
    {
        if (! $mapName) {
            return false;
        }

        $read = self::readPhysics($physics);

        if ($read === null) {
            return false;
        }

        return isset(self::pairs()[self::key($mapName, $read[0], $read[1])]);
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

        return self::keySql($mapColumn, $physicsColumn) . " NOT IN ({$quoted})";
    }

    /**
     * The same key readPhysics() makes, built in SQL from a stored physics
     * string: `CPM` and `CPM.TR` are runs, `CPM.2` is ctf2.
     */
    public static function keySql(string $mapColumn, string $physicsColumn): string
    {
        $suffix = "SUBSTRING_INDEX({$physicsColumn}, '.', -1)";

        return "CONCAT("
            . "LOWER({$mapColumn}), '|', "
            . "LOWER(SUBSTRING_INDEX({$physicsColumn}, '.', 1)), '|', "
            . "CASE WHEN {$physicsColumn} LIKE '%.%' AND {$suffix} REGEXP '^[0-9]+$' "
            . "THEN CONCAT('ctf', CAST({$suffix} AS UNSIGNED)) ELSE '" . self::MODE . "' END"
            . ")";
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function key(string $mapName, string $physics, string $mode): string
    {
        // `CPM` on rendered_videos, `cpm` on records. Same map either way.
        return strtolower(trim($mapName)) . '|' . strtolower(trim($physics)) . '|' . strtolower(trim($mode));
    }
}
