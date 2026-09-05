<?php

namespace App\Services;

use App\Models\RenderedVideo;
use App\Models\YoutubePlaylist;
use App\Services\Comps\MapClassifier;
use App\Services\JokeMaps;
use App\Services\Comps\MapEligibilityTagger;
use Illuminate\Support\Facades\DB;

/**
 * Works out what belongs in each of the channel's playlists.
 *
 * Two rules run through everything here.
 *
 * One video per map and physics. A map with four rendered runs would otherwise
 * put four near-identical videos in a row, and a playlist that repeats itself
 * stops being a shelf and becomes a pile. Only the fastest survives, so every
 * playlist reads as a best-of.
 *
 * A combined playlist holds both physics, and interleaves them. The same map
 * appears twice, once per physics, and those two runs look alike - so they are
 * pushed to opposite halves of the list rather than left side by side. The
 * interleave is worked out from the sort order and never from chance: a shuffle
 * that came out differently on every run would make an update cost as much as a
 * rebuild.
 *
 * Nothing here talks to YouTube. It writes a snapshot the bot then carries out,
 * so the counts the admin sees are the ones that run.
 */
class YoutubePlaylistService
{
    /**
     * The guns worth a shelf of their own, as the comps classifier names them.
     */
    private const WEAPON_TITLES = [
        'rl' => 'Rocket',
        'pg' => 'Plasma',
        'gl' => 'Grenade',
        'lg' => 'Lightning',
        'bfg' => 'BFG',
    ];

    /**
     * Runs are sorted into these by length. The last one is open-ended, and
     * the two beyond ten minutes are the tiers the render queue already uses.
     */
    private const TIME_BUCKETS = [
        ['key' => 'sub10', 'title' => 'Under 10 Seconds', 'from' => 0, 'to' => 10000],
        ['key' => '10s', 'title' => '10-19 Seconds', 'from' => 10000, 'to' => 20000],
        ['key' => '20s', 'title' => '20-29 Seconds', 'from' => 20000, 'to' => 30000],
        ['key' => '30s', 'title' => '30-39 Seconds', 'from' => 30000, 'to' => 40000],
        ['key' => '40s', 'title' => '40-49 Seconds', 'from' => 40000, 'to' => 50000],
        ['key' => '50s', 'title' => '50-59 Seconds', 'from' => 50000, 'to' => 60000],
        ['key' => '1m', 'title' => '1-2 Minutes', 'from' => 60000, 'to' => 120000],
        ['key' => '2m', 'title' => '2-5 Minutes', 'from' => 120000, 'to' => 300000],
        ['key' => '5m', 'title' => '5-10 Minutes', 'from' => 300000, 'to' => 600000],
        ['key' => '10m', 'title' => '10-50 Minutes', 'from' => 600000, 'to' => 3000000],
        ['key' => '50m', 'title' => 'Over 50 Minutes', 'from' => 3000000, 'to' => null],
    ];

    private const PREFIX = 'Quake 3 Defrag - ';

    /**
     * Every playlist the channel can have, keyed by its short name.
     *
     * A style or gun gets three: both physics together, then one each. The
     * combined one is what most people click; the split ones are for a player
     * who only runs one physics and does not want the other in the way. Time
     * buckets get one each - they are a way to browse, not a side to pick, and
     * splitting them would put another twenty-two shelves on the channel.
     *
     * @return array<string, array{title: string, group: string, physics: string|null}>
     */
    public function definitions(): array
    {
        $out = [];

        $split = function (string $key, string $title, string $group) use (&$out) {
            $out[$key] = ['title' => self::PREFIX . $title, 'group' => $group, 'physics' => null];
            $out[$key . '_cpm'] = ['title' => self::PREFIX . $title . ' (CPM)', 'group' => $group, 'physics' => 'cpm'];
            $out[$key . '_vq3'] = ['title' => self::PREFIX . $title . ' (VQ3)', 'group' => $group, 'physics' => 'vq3'];
        };

        $split('wr', 'World Records', 'Records');
        $split('strafe', 'Strafe', 'Style');

        foreach (self::WEAPON_TITLES as $gun => $title) {
            $split('weapon_' . $gun, $title, 'Style');
        }

        $split('combo', 'Combo', 'Style');

        foreach (self::TIME_BUCKETS as $bucket) {
            $out['time_' . $bucket['key']] = [
                'title' => self::PREFIX . $bucket['title'],
                'group' => 'Length',
                'physics' => null,
            ];
        }

        return $out;
    }

    public function description(string $key): string
    {
        $title = $this->definitions()[$key]['title'] ?? 'Defrag';

        return "{$title}. Defrag is the Quake 3 movement mod: no shooting, just"
            . " speed. Every run here is the fastest recorded on that map."
            . "\n\nTimes, demos and rankings: https://defrag.racing";
    }

    /**
     * Work out the contents of the given playlists and store them.
     *
     * @param  string[]  $keys
     * @return array<string, int> key => how many videos it should now hold
     */
    public function snapshot(array $keys): array
    {
        $planned = $this->compute($keys);
        $counts = [];

        foreach ($keys as $key) {
            $videoIds = $planned[$key] ?? [];

            $playlist = YoutubePlaylist::firstOrCreate(['key' => $key]);

            // Replaced wholesale rather than diffed: this is a snapshot of what
            // the playlist should hold, and the diff that matters is the one
            // the bot makes against YouTube itself.
            DB::table('youtube_playlist_items')->where('youtube_playlist_id', $playlist->id)->delete();

            $rows = [];
            $now = now();

            foreach ($videoIds as $position => $videoId) {
                $rows[] = [
                    'youtube_playlist_id' => $playlist->id,
                    'rendered_video_id' => $videoId,
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('youtube_playlist_items')->insert($chunk);
            }

            $playlist->update([
                'planned_count' => count($videoIds),
                'sync_queued' => true,
                'computed_at' => $now,
            ]);

            $counts[$key] = count($videoIds);
        }

        return $counts;
    }

    /**
     * @param  string[]  $keys
     * @return array<string, int[]> key => rendered_video ids in playlist order
     */
    public function compute(array $keys): array
    {
        $definitions = $this->definitions();
        $keys = array_values(array_filter($keys, fn ($k) => isset($definitions[$k])));

        if (! $keys) {
            return [];
        }

        $videos = $this->candidates();
        $out = [];

        foreach ($keys as $key) {
            $definition = $definitions[$key];
            $matching = array_filter($videos, fn ($v) => $this->belongs($v, $key, $definition));

            $out[$key] = $this->order($this->fastestPerMap($matching), $definition['physics']);
        }

        return $out;
    }

    /**
     * Every video that may appear in a playlist, with what its map is made of.
     *
     * Public only. An unlisted video sits in a playlist as a row nobody can
     * open, so a playlist built from the whole queue would look broken to
     * everyone but the channel owner.
     */
    private function candidates(): array
    {
        $strafeTagged = DB::table('map_tag')
            ->join('tags', 'tags.id', '=', 'map_tag.tag_id')
            ->where('tags.name', MapClassifier::STRAFE_TAG)
            ->pluck('map_tag.map_id')
            ->flip()
            ->all();

        // A map tagged `cpmonly` cannot be finished in vq3, and the other way
        // round. A run in the barred physics is either a mistagged map or a
        // run that never really ended, and either way it must not head a
        // playlist as that map's fastest time. Same pair comps bars on.
        $barred = [];

        foreach (MapEligibilityTagger::ONLY_TAG as $physics => $tagName) {
            foreach (DB::table('map_tag')
                ->join('tags', 'tags.id', '=', 'map_tag.tag_id')
                ->where('tags.name', $tagName)
                ->pluck('map_tag.map_id') as $mapId) {
                $barred[$mapId] = $physics;
            }
        }

        $classifier = new MapClassifier();
        $out = [];

        RenderedVideo::query()
            ->where('rendered_videos.status', 'completed')
            ->where('rendered_videos.is_visible', true)
            ->whereNotNull('rendered_videos.youtube_video_id')
            ->whereNotNull('rendered_videos.published_at')
            ->where('rendered_videos.time_ms', '>', 0)
            ->leftJoin('maps', 'maps.name', '=', 'rendered_videos.map_name')
            ->select(
                'rendered_videos.id',
                'rendered_videos.map_name',
                'rendered_videos.physics',
                'rendered_videos.time_ms',
                'rendered_videos.quality_tier',
                'maps.id as map_id',
                'maps.weapons as map_weapons',
            )
            ->orderBy('rendered_videos.id')
            ->chunk(2000, function ($rows) use (&$out, $classifier, $strafeTagged) {
                foreach ($rows as $row) {
                    // `CPM.TR`, `vq3-fastcap`: only the first word is the
                    // physics, the rest says which ruleset the run was under.
                    $physics = strtolower((string) preg_split('/[.\-_ ]/', (string) $row->physics)[0]);

                    if (! in_array($physics, ['cpm', 'vq3'], true)) {
                        continue;
                    }

                    if ($row->map_id !== null && ($barred[$row->map_id] ?? null) === $physics) {
                        continue;
                    }

                    // A playlist of the fastest run on each map has no use for
                    // a map where a hundred people share the fastest run.
                    if (JokeMaps::isJoke($row->map_name, $physics)) {
                        continue;
                    }

                    $verdict = $classifier->classify(
                        $row->map_weapons,
                        $row->map_id !== null && isset($strafeTagged[$row->map_id])
                    );

                    $out[] = [
                        'id' => (int) $row->id,
                        'map' => (string) $row->map_name,
                        'physics' => $physics,
                        'time_ms' => (int) $row->time_ms,
                        'tier' => (int) $row->quality_tier,
                        'category' => $verdict['category'],
                        'weapon' => $verdict['weapon'],
                    ];
                }
            });

        return $out;
    }

    private function belongs(array $video, string $key, array $definition): bool
    {
        if ($definition['physics'] !== null && $video['physics'] !== $definition['physics']) {
            return false;
        }

        $base = preg_replace('/_(cpm|vq3)$/', '', $key);

        if ($base === 'wr') {
            return $video['tier'] === \App\Services\RenderQueueService::TIER_ONLINE_WR;
        }

        if ($base === 'strafe') {
            return $video['category'] === MapClassifier::STRAFE;
        }

        if ($base === 'combo') {
            return $video['category'] === MapClassifier::COMBO;
        }

        if (str_starts_with($base, 'weapon_')) {
            return $video['category'] === MapClassifier::WEAPON
                && $video['weapon'] === substr($base, strlen('weapon_'));
        }

        if (str_starts_with($base, 'time_')) {
            $bucket = $this->bucket(substr($base, strlen('time_')));

            return $bucket !== null
                && $video['time_ms'] >= $bucket['from']
                && ($bucket['to'] === null || $video['time_ms'] < $bucket['to']);
        }

        return false;
    }

    private function bucket(string $key): ?array
    {
        foreach (self::TIME_BUCKETS as $bucket) {
            if ($bucket['key'] === $key) {
                return $bucket;
            }
        }

        return null;
    }

    /**
     * One run per map and physics: the fastest. The id breaks a tie so the same
     * run wins every time this is computed, which is what keeps an update from
     * turning into a rebuild.
     */
    private function fastestPerMap(array $videos): array
    {
        $best = [];

        foreach ($videos as $video) {
            $slot = $video['map'] . '|' . $video['physics'];
            $held = $best[$slot] ?? null;

            if ($held === null
                || $video['time_ms'] < $held['time_ms']
                || ($video['time_ms'] === $held['time_ms'] && $video['id'] < $held['id'])) {
                $best[$slot] = $video;
            }
        }

        return array_values($best);
    }

    /**
     * @return int[] rendered_video ids, in the order they go into the playlist
     */
    private function order(array $videos, ?string $physics): array
    {
        usort($videos, fn ($a, $b) => [$a['map'], $a['physics']] <=> [$b['map'], $b['physics']]);

        if ($physics !== null) {
            return array_column($videos, 'id');
        }

        // Both physics in one playlist, so the same map is in here twice and
        // the two runs look alike. Reversing one side puts each pair at
        // opposite ends, and alternating them keeps a viewer from getting two
        // of one physics in a row.
        //
        // Reversed rather than rotated by half, which is what this did first.
        // A rotation is measured from the middle, so one new map moved the
        // middle and reordered the whole side - and an update that reorders
        // everything costs as much as a rebuild. Each side is now in its own
        // fixed order whatever its length, so a new map is one insert at one
        // position and nothing else moves.
        $cpm = array_values(array_filter($videos, fn ($v) => $v['physics'] === 'cpm'));
        $vq3 = array_reverse(array_values(array_filter($videos, fn ($v) => $v['physics'] === 'vq3')));

        $out = [];

        for ($i = 0; $i < max(count($cpm), count($vq3)); $i++) {
            if (isset($cpm[$i])) {
                $out[] = $cpm[$i]['id'];
            }

            if (isset($vq3[$i])) {
                $out[] = $vq3[$i]['id'];
            }
        }

        return $out;
    }
}
