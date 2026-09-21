<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Record;
use App\Models\OldtopRecord;
use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use App\Models\User;
use App\Models\Map;
use App\Models\MddProfile;
use App\Models\RecordFlag;
use App\Models\PlayerMapScore;
use App\Models\MapDifficultyRating;
use App\Models\RecordHistory;

use App\Filters\MapFilters;
use App\Services\NameMatcher;
use App\Services\VirtualPlayerGrouper;
use App\Services\DemoProfileResolver;

class MapsController extends Controller
{
    public function index(Request $request) {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        // If there are any filter parameters (except page), redirect to filters route
        // But not for partial requests (tag filtering uses partial reload)
        $filterParams = $request->except(['page']);
        if (!empty($filterParams) && !$isPartial) {
            return redirect()->route('maps.filters', $request->all());
        }

        if (!$isPartial) {
            return Inertia::render('Maps')->with('maps', null);
        }

        $maps = Map::query()
            ->select('id', 'name', 'author', 'pk3', 'thumbnail', 'physics', 'gametype', 'weapons', 'items', 'functions', 'is_nsfw', 'date_added', 'created_at')
            ->withAvg('difficultyRatings', 'rating')
            ->withCount('difficultyRatings')
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate(16)
            ->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            return redirect()->route('maps', ['page' => $maps->lastPage()]);
        }

        return Inertia::render('Maps')->with('maps', $this->attachPlayed($request, $maps));
    }

    public function filters(Request $request) {
        $isPartial = $request->header('X-Inertia-Partial-Data') !== null;

        $mapFilters = (new MapFilters())->filter($request);
        $queries = $mapFilters['data'];

        if (!$isPartial) {
            return Inertia::render('Maps')
                ->with('maps', null)
                ->with('queries', $queries);
        }

        $maps = $mapFilters['query'];
        $maps = $maps->paginate(16)->withQueryString();

        if ($request->has('page') && $request->get('page') > $maps->lastPage()) {
            $paging = ['page' => $maps->lastPage()];
            return redirect()->route('maps.filters', array_merge($paging, $queries));
        }

        return Inertia::render('Maps')
            ->with('maps', $this->attachPlayed($request, $maps))
            ->with('queries', $queries);
    }

    /**
     * Mark which maps on this page the logged in player has already run.
     *
     * Played means "has an MDD record here", because a record is the only
     * trace the site ever sees - loading a map and not finishing leaves
     * nothing behind. Worth saying out loud when someone asks why a map they
     * remember playing is not marked.
     *
     * The badge follows the filters that are on screen. With no physics or
     * gametype filter, any record counts and the card says nothing about
     * which one. Filter the list to VQ3 and only a VQ3 record marks the map,
     * so the badge can never claim a run the filtered list would not show.
     *
     * One extra query per page of 16 maps, on (mdd_id, mapname).
     */
    private function attachPlayed(Request $request, $maps)
    {
        $mddId = $request->user()?->mdd_id;

        if (! $mddId) {
            return $maps;
        }

        $names = $maps->getCollection()->pluck('name')->filter();

        if ($names->isEmpty()) {
            return $maps;
        }

        $physics = $this->playedPhysics($request);
        $modes = $this->playedModes($request);

        $played = Record::where('mdd_id', $mddId)
            ->whereIn('mapname', $names)
            ->when($physics, fn ($query) => $query->where('physics', $physics))
            ->when($modes, fn ($query) => $query->whereIn('mode', $modes))
            ->select('mapname', 'physics')
            ->distinct()
            ->get()
            // Keyed lowercase on both sides. MySQL matched `bardok-2026`
            // against the map called `BardoK-2026` happily, being
            // case-insensitive, and then PHP did not - so a map with a
            // capital in its name never got the badge.
            ->groupBy(fn ($record) => mb_strtolower($record->mapname));

        $maps->getCollection()->transform(function ($map) use ($played) {
            $found = $played->get(mb_strtolower($map->name))?->pluck('physics')->unique();

            $map->played = $found !== null;
            $map->played_physics = match (true) {
                $found === null => null,
                $found->count() > 1 => 'both',
                default => $found->first(),
            };

            return $map;
        });

        return $maps;
    }

    /**
     * The physics a record has to be in to count, or null for any of them.
     * Both boxes ticked is the same as neither.
     */
    private function playedPhysics(Request $request): ?string
    {
        $physics = array_map('strtolower', array_filter((array) $request->input('physics', [])));
        $physics = array_values(array_intersect($physics, ['vq3', 'cpm']));

        return count($physics) === 1 ? $physics[0] : null;
    }

    /**
     * The record modes that count, or an empty list for any of them.
     *
     * `records.mode` only ever holds `run` and `ctf1`..`ctf7`, so fastcaps is
     * the one gametype worth narrowing by, and any of the seven does. Team
     * and freestyle runs are written as plain `run` rows, and the map list is
     * already filtered to those maps anyway, so narrowing there would only
     * hide runs the player really did.
     */
    private function playedModes(Request $request): array
    {
        $gametypes = array_map('strtolower', array_filter((array) $request->input('gametype', [])));
        $modes = [];

        foreach ($gametypes as $gametype) {
            if ($gametype === 'run') {
                $modes[] = 'run';
                continue;
            }

            if ($gametype === 'fastcaps') {
                $modes = array_merge($modes, ['ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7']);
                continue;
            }

            return [];
        }

        return array_values(array_unique($modes));
    }

    /**
     * Fake time history for a given demo on a map, reconstructed from all
     * demo uploads matched to the same virtual player via
     * name/q3df_colored/q3df_plain (transitive OR match).
     *
     * Query params:
     *   demo_id : int - UploadedDemo.id to use as seed
     *   physics : 'vq3' | 'cpm'
     */
    /**
     * The fastcap a demo belongs to, or null for a normal run.
     *
     * uploaded_demos.physics carries the mode in a suffix: "CPM" for a run,
     * "CPM.TR" for a time reset, "CPM.0" through "CPM.8" for the fastcaps.
     */
    private static function fastcapOf(?string $demoPhysics): ?string
    {
        return preg_match('/\.(\d)$/', (string) $demoPhysics, $m) ? $m[1] : null;
    }

    /**
     * Narrow a demo query to one gametype.
     *
     * A ctf2 list must not see CPM.1, and a defrag run must not see fastcaps
     * at all - they are different gametypes, not variants of one.
     */
    private function scopeDemoPhysics($query, string $physics, ?string $fastcap)
    {
        $base = strtoupper($physics);

        if ($fastcap !== null) {
            return $query->where('physics', $base . '.' . $fastcap);
        }

        // A run, so everything except the numbered fastcaps. ".TR" stays: a
        // trick run is the same run, recorded by somebody showing off.
        return $query->where(function ($q) use ($base) {
            $q->where('physics', $base)
                ->orWhere('physics', $base . '.TR');
        });
    }

    /**
     * The demos on a map that has no leaderboard to put them in.
     *
     * A freestyle map has no timer, so nothing it holds can be ranked, and its
     * page was two empty tables however many demos people had uploaded to it -
     * csu1_a alone carries 1617. The same happens to a run map nobody has ever
     * set a record on. 88 maps and 4176 demos site-wide, all of them with a
     * thumbnail, an author and a pk3 already in hand.
     *
     * Returns null when the map does have times to show, so the page keeps its
     * leaderboards and nothing else changes.
     */
    private function untimedDemos($map): ?\Illuminate\Support\Collection
    {
        // Everything on the map that is not a timed run: freestyle, and any
        // demo with no time at all - a trick, a tutorial, a run that never
        // finished. Neither can be ranked and neither belongs in a history of
        // times, so without this list they are reachable from nowhere. 183 maps
        // hold both these and real records (cos1_beta7b has 267 freestyle demos
        // beside 21 records), which is why the page offers both.
        //
        // Grouped by player, one row each, opening to their demos. Freestyle is
        // not one run per person: on csu1_a two people have a hundred apiece,
        // and 1523 demos come down to 258 names.
        $base = fn (string $physics) => UploadedDemo::where('map_name', $map->name)
            // failed-validity belongs here too: it means the demo deviated on
            // some cvar, not that it is unusable, and 567 freestyle demos carry
            // it. They are listed with the flag on a chip, same as everywhere.
            ->whereIn('status', ['assigned', 'fallback-assigned', 'processed', 'failed-validity'])
            ->where(fn ($q) => $q->where('physics', $physics)->orWhere('physics', 'LIKE', $physics . '.%'))
            ->where(fn ($q) => $q->whereIn('gametype', ['fs', 'mfs'])->orWhereNull('time_ms'));

        // Grouping needs the identity fields and nothing else, so the whole map
        // is read cheaply; only the demos on the page being looked at get their
        // relations loaded further down.
        $light = fn (string $physics) => $base($physics)
            ->orderByDesc('record_date')->orderByDesc('created_at')
            ->get(['id', 'player_name', 'q3df_login_name', 'q3df_login_name_colored',
                   'assigned_user_id', 'record_date', 'created_at']);

        $vq3Light = $light('VQ3');
        $cpmLight = $light('CPM');

        if ($vq3Light->isEmpty() && $cpmLight->isEmpty()) {
            return null;
        }

        // Attribute them the way everything else on the site does: approved
        // aliases, through the same resolver Demos Top clusters with. A
        // freestyle demo can never hang off a record, so without this the rows
        // showed a question mark for an avatar and led nowhere, even for people
        // whose nick the site knows perfectly well.
        //
        // Deliberately NOT suggested_user_id, which 11596 of the 11640
        // freestyle demos carry: that is the fuzzy matcher's guess, and putting
        // a guess under somebody's avatar asserts the run is theirs.
        $resolver = new DemoProfileResolver();
        $priorityKeys = Record::where('mapname', $map->name)
            ->select(['user_id', 'mdd_id'])->get()
            ->flatMap(fn ($r) => array_filter([
                $r->user_id ? 'user:' . (int) $r->user_id : null,
                $r->mdd_id ? 'mdd:' . (int) $r->mdd_id : null,
            ]))->unique()->values()->all();

        // No profile: fall back to the nick on the demo, colours stripped, so
        // somebody the site does not know still gets one row instead of forty.
        // Only ever merges identical nicks, never two different ones.
        // A staff assignment outranks the resolver: somebody looked at the demo
        // and said whose it is, which beats matching a nick.
        $keyOf = fn ($d) => ($d->assigned_user_id ? 'user:' . $d->assigned_user_id : null)
            ?: $resolver->resolve($d, $priorityKeys)
            ?: 'name:' . strtolower(trim(preg_replace('/\^[0-9a-zA-Z]/', '', $d->player_name ?? '')));

        // Biggest first, the way a freestyle map reads: who has been at it.
        $group = fn ($rows) => $rows->groupBy($keyOf)
            ->map(fn ($g) => $g->pluck('id')->all())
            ->sortByDesc(fn ($ids) => count($ids));

        $paginate = function ($groups, string $pageName) {
            $page = max(1, (int) request()->input($pageName, 1));

            return new \Illuminate\Pagination\LengthAwarePaginator(
                // Ten a page, not fifty: the groups arrive open, and the
                // biggest ones here run to a hundred demos apiece.
                $groups->slice(($page - 1) * 10, 10),
                $groups->count(),
                10,
                $page,
                ['path' => request()->url(), 'pageName' => $pageName, 'query' => request()->query()]
            );
        };

        $vq3Page = $paginate($group($vq3Light), 'vq3DemosPage');
        $cpmPage = $paginate($group($cpmLight), 'cpmDemosPage');

        // values() before merging: both are keyed by profile key, and a
        // player with demos in both physics would otherwise have one side
        // silently overwrite the other.
        $shownIds = collect($vq3Page->items())->values()
            ->merge(collect($cpmPage->items())->values())
            ->flatten()->unique()->values();

        $demos = UploadedDemo::whereIn('id', $shownIds)
            ->with(['renderedVideo', 'user', 'record.user'])
            ->orderByDesc('record_date')->orderByDesc('created_at')
            ->get()->keyBy('id');

        $validityFlags = $this->validityFlagsByDemo($shownIds->all());

        $users = User::whereIn('id', collect($vq3Page->items())->keys()
            ->merge(collect($cpmPage->items())->keys())
            ->filter(fn ($k) => str_starts_with($k, 'user:'))
            ->map(fn ($k) => (int) substr($k, 5))->unique()->values())
            ->get()->keyBy('id');

        // MapRecord's shape, so the demos inside a group get the same chips,
        // download button, video and report actions the leaderboard rows have.
        $shape = function ($d, $owner, $mddId) use ($validityFlags) {
            $isOnline = $d->gametype && str_starts_with($d->gametype, 'm');

            return [
                'id' => $d->id,
                'demo_id' => $d->id,
                'record_id' => $d->record_id,
                'time' => $d->time_ms,
                'time_ms' => $d->time_ms,
                'date_set' => $d->record_date ?? $d->created_at,
                'player_name' => $d->player_name,
                'name' => $owner?->name ?? $d->player_name,
                'country' => $d->country ?? $owner?->country ?? '_404',
                'is_online' => $isOnline,
                'verification_type' => $validityFlags[$d->id]
                    ?? ($d->record_id ? 'verified' : ($isOnline ? 'ONLINE' : 'OFFLINE')),
                'rank' => null,
                'user' => $owner,
                'mdd_id' => $mddId,
                'demo' => $d,
                'demo_label' => $this->demoLabel($d),
                'assigned_user_id' => $d->assigned_user_id,
                'uploaded_demos' => [],
                'rendered_videos' => $d->renderedVideo ? [$d->renderedVideo] : [],
                'q3df_login_name' => $d->q3df_login_name,
                'q3df_login_name_colored' => $d->q3df_login_name_colored,
            ];
        };

        $buildGroups = function ($paginator) use ($demos, $users, $shape) {
            $out = [];

            foreach ($paginator->items() as $key => $ids) {
                $owner = str_starts_with($key, 'user:') ? $users->get((int) substr($key, 5)) : null;
                $mddId = str_starts_with($key, 'mdd:') ? (int) substr($key, 4) : null;

                $rows = collect($ids)->map(fn ($id) => $demos->get($id))->filter()
                    ->map(fn ($d) => $shape($d, $owner, $mddId))->values();

                if ($rows->isEmpty()) {
                    continue;
                }

                $out[] = [
                    'key' => $key,
                    'name' => $owner?->name ?? $rows->first()['player_name'],
                    'user' => $owner,
                    'mdd_id' => $mddId,
                    'country' => $rows->first()['country'],
                    'count' => $rows->count(),
                    'latest' => $rows->first()['date_set'],
                    'demos' => $rows,
                ];
            }

            $paginator->setCollection(collect($out));

            return $paginator;
        };

        return collect([
            'vq3' => $buildGroups($vq3Page),
            'cpm' => $buildGroups($cpmPage),
        ]);
    }

    /**
     * What the uploader called the demo, with everything the row already shows
     * taken out: the map, the mode brackets, the (player.country) and the
     * {cvars}. On a freestyle map that leftover is the whole point of the demo
     * - "oups-fs-b1_jpad_1xR_3xR" or "Szak_white2-to-blue1" - and with no time
     * to tell the runs apart it is the only thing that does.
     */
    private function demoLabel(UploadedDemo $d): ?string
    {
        return \App\Services\VideoMetadataService::demoLabel(
            $d->original_filename ?: $d->processed_filename,
            $d->map_name
        );
    }

    /**
     * The mode a demo was recorded in, off its gametype. The 'm' prefix only
     * says the run happened online, so it does not change the mode.
     */
    private const GAMETYPE_FAMILIES = [
        'df' => 'run',      'mdf' => 'run',
        'fc' => 'fastcap',  'mfc' => 'fastcap',
        'fs' => 'freestyle', 'mfs' => 'freestyle',
    ];

    private static function familyOf(?string $gametype): ?string
    {
        return self::GAMETYPE_FAMILIES[$gametype] ?? null;
    }

    /**
     * Keep a demo set to one mode. Physics alone does not separate them: a
     * freestyle demo is stored as `[fs.cpm.2]` -> physics `CPM.2`, the same
     * value a fastcap run on flag 2 carries, so the fastcap's time history was
     * pulling in freestyle demos and listing them at 00.000 because freestyle
     * has no time to show. Reported by Enter with prince_quake and cos1_beta7b;
     * measured there, 20 of the 36 demos behind one fastcap run were freestyle.
     *
     * Demos with no gametype at all (2947, all but 24 of them failed uploads)
     * are left in rather than silently dropped - they are unclassifiable, not
     * known to be a different mode.
     */
    private function scopeDemoFamily($query, ?string $family)
    {
        // A history of times cannot hold a demo with no time. Tricks, tutorials
        // and runs that never finished were listed at 00.000 among real
        // attempts; they belong in the map's demo list, which is where they are
        // now. Applied whatever the mode, so it also covers a trick recorded in
        // a fastcap or a run slot.
        $query->whereNotNull('time_ms');

        if ($family === null) {
            return $query;
        }

        $types = array_keys(array_filter(
            self::GAMETYPE_FAMILIES,
            fn ($f) => $f === $family
        ));

        return $query->where(function ($q) use ($types) {
            $q->whereIn('gametype', $types)->orWhereNull('gametype');
        });
    }

    /**
     * Demos a history must not contain at all. Only approved community flags
     * count: somebody looked at the run and decided it is not what it claims.
     *
     * Parser validity flags are deliberately NOT in here. `validity_flag` holds
     * whatever cvar the run deviated on - `sv_fps=120.0` is the biggest bucket
     * on the site - and DemosTopService already lists those demos on the map
     * with the flag on a chip. Excluding them from the player's own history as
     * well left runs that nothing on the site could reach: too slow for Demos
     * Top against their own MDD record, and refused by the drawer.
     */
    /**
     * The MDD record time on this map for every profile that holds one, keyed
     * "user:<id>" and "mdd:<id>". Both keys are emitted for the same record so
     * a lookup works whether the q3df profile has been claimed here or not,
     * and the fastest wins if a profile somehow holds two.
     *
     * This is the line that decides which row a demo hangs under. Everything
     * at or slower than the record belongs to the record's own drawer; only
     * what is faster than the record, and no faster than the online row
     * itself, belongs to that online row. Without it both drawers listed the
     * whole cluster, so the same attempt showed up twice on one screen and a
     * faster demo sat nested under a slower one.
     */
    private function mddTimesByProfileKey(string $mapname, string $gametype): array
    {
        $times = [];

        Record::where('mapname', $mapname)
            ->where('gametype', $gametype)
            ->select(['user_id', 'mdd_id', 'time'])
            ->get()
            ->each(function ($r) use (&$times) {
                $t = (int) $r->time;

                foreach (array_filter([
                    $r->user_id ? 'user:' . (int) $r->user_id : null,
                    $r->mdd_id ? 'mdd:' . (int) $r->mdd_id : null,
                ]) as $key) {
                    if (!isset($times[$key]) || $t < $times[$key]) {
                        $times[$key] = $t;
                    }
                }
            });

        return $times;
    }

    /** The gametype string records are stored under: run_cpm, ctf3_vq3, ... */
    private static function recordGametype(string $physics, ?string $fastcap = null): string
    {
        return ($fastcap !== null ? 'ctf' . $fastcap : 'run') . '_' . $physics;
    }

    private function communityFlaggedDemoIds(array $demoIds, array $recordIds): array
    {
        if (empty($demoIds) && empty($recordIds)) {
            return [];
        }

        return RecordFlag::where('status', 'approved')
            ->where(function ($q) use ($demoIds, $recordIds) {
                if (!empty($demoIds)) $q->whereIn('demo_id', $demoIds);
                if (!empty($recordIds)) $q->orWhereIn('record_id', $recordIds);
            })
            ->pluck('demo_id')->filter()->unique()->values()->all();
    }

    /**
     * demo_id => validity flag, for the demos that carry one. Fed into the
     * history rows as `verification_type` so the drawer shows the same red chip
     * the map page does instead of quietly calling the run ONLINE.
     */
    private function validityFlagsByDemo(array $demoIds): array
    {
        if (empty($demoIds)) {
            return [];
        }

        return OfflineRecord::whereIn('demo_id', $demoIds)
            ->whereNotNull('validity_flag')
            ->where('validity_flag', '!=', '')
            ->pluck('validity_flag', 'demo_id')
            ->all();
    }

    public function timeHistory(Request $request, $mapname)
    {
        $demoId = (int) $request->input('demo_id');
        $userId = (int) $request->input('user_id');
        $mddId = (int) $request->input('mdd_id');
        $physics = strtolower((string) $request->input('physics'));

        if ((!$demoId && !$userId && !$mddId) || !in_array($physics, ['vq3', 'cpm'], true)) {
            return response()->json(['error' => 'Invalid parameters'], 422);
        }

        // Profile mode: main record has no attached demo of its own. Use
        // the profile identity (registered user_id or unclaimed mdd_id) as
        // the virtual seed - no real demo to exclude, so every online
        // cluster member shows in the drawer.
        if (!$demoId && ($userId || $mddId)) {
            $profileKey = $userId ? ('user:' . $userId) : ('mdd:' . $mddId);
            // No seed demo to read the fastcap off, so the caller says which
            // one it is looking at. Absent, it behaves as before.
            $viewed = preg_match('/^ctf(\d)/', (string) $request->input('gametype'), $ctf) ? $ctf[1] : null;

            return $this->timeHistoryForProfile($mapname, $physics, $profileKey, $viewed);
        }

        $seed = UploadedDemo::find($demoId);
        if (!$seed) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // Fetch all demos on this map+physics (both online-assigned and offline-only).
        // The fastcap the seed belongs to decides the set: a ctf1 attempt has
        // nothing to do with the same player's ctf2 attempt, and before this
        // they landed in one history together.
        $demos = UploadedDemo::where('map_name', $mapname)
            ->where(fn ($q) => $this->scopeDemoPhysics($q, $physics, self::fastcapOf($seed->physics)))
            ->where(fn ($q) => $this->scopeDemoFamily($q, self::familyOf($seed->gametype)))
            ->with(['renderedVideo', 'user', 'record.user'])
            ->get();

        // Drop community-flagged demos before clustering: an approved RecordFlag
        // is a human verdict that the run is not what it claims, and such a demo
        // has no business in anyone's history - not as the seed, and not in
        // someone else's drawer just because the name matches.
        $demoIds = $demos->pluck('id')->all();
        $recordIds = $demos->pluck('record_id')->filter()->all();
        $flaggedDemoIds = $this->communityFlaggedDemoIds($demoIds, $recordIds);
        if (in_array($seed->id, $flaggedDemoIds, true)) {
            return response()->json(['history' => [], 'signals' => 0]);
        }
        $flaggedSet = array_flip($flaggedDemoIds);
        $demos = $demos->reject(fn ($d) => isset($flaggedSet[$d->id]))->values();

        // Parser validity flags stay in. They are the cvar the run was set
        // under, not a verdict, and the map page already lists those demos with
        // the flag on a chip - hiding the same demo from the player's own
        // history left runs reachable from nowhere at all. Carried through so
        // the drawer shows the same chip.
        $validityFlags = $this->validityFlagsByDemo($demoIds);

        $grouper = new VirtualPlayerGrouper();
        $cluster = $grouper->classFor($demos, $seed);

        if ($cluster->isEmpty()) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // History filtering:
        //   1. Exclude the seed demo itself - main row already shows it
        //   2. Filter by is_online matching the seed - Demos Top shows
        //      online and offline reps as separate rows, each with its own
        //      time history drawer of the same origin type (so an offline
        //      rep's drawer doesn't pull in online attempts and vice versa)
        //   3. Slower than the seed, and faster than the player's own MDD
        //      record. A demo at or slower than the record hangs under the
        //      record's row instead, which is the only row that can claim it
        //      without putting a faster time underneath a slower one.
        // Distinct demo IDs are all shown: two demos with identical time_ms
        // or file_hash but different demo.id are intentionally kept - user
        // wants to see every attempt as long as it's a separate record.
        $seedId = (int) $seed->id;
        $seedIsOnline = $seed->gametype && str_starts_with($seed->gametype, 'm');
        $seedTime = (int) $seed->time_ms;

        // Only online demos ever reach a record's drawer, so an offline rep
        // has nothing to cede and keeps its whole subcluster.
        $mddTime = null;
        if ($seedIsOnline) {
            $mddTimes = $this->mddTimesByProfileKey(
                $mapname,
                self::recordGametype($physics, self::fastcapOf($seed->physics))
            );
            $seedKey = (new DemoProfileResolver())->resolve($seed, array_keys($mddTimes));
            $mddTime = $seedKey !== null ? ($mddTimes[$seedKey] ?? null) : null;
        }

        $cluster = $cluster
            ->reject(fn ($d) => (int) $d->id === $seedId)
            ->filter(function ($d) use ($seedIsOnline, $seedTime, $mddTime) {
                $isOnline = $d->gametype && str_starts_with($d->gametype, 'm');
                if ($isOnline !== $seedIsOnline) {
                    return false;
                }

                $time = (int) $d->time_ms;

                if ($time < $seedTime) {
                    return false;
                }

                return $mddTime === null || $time < $mddTime;
            })
            ->sortBy('time_ms')
            ->values();

        if ($cluster->isEmpty()) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // Signal count against the seed - how confidently did we group this?
        $maxSignals = 0;
        foreach ($cluster as $d) {
            if ((int) $d->id === (int) $seed->id) continue;
            $s = $grouper->signalStrength($seed, $d);
            if ($s > $maxSignals) $maxSignals = $s;
        }

        // The seed demo (the row the user clicked) acts as the canonical
        // representation of the virtual player - all history rows inherit its
        // avatar/country so the leaderboard tells a consistent story.
        //
        // Using the seed (not cluster->first) because we just filtered out the
        // top time from the cluster, but the seed itself is that top time and
        // already has the authoritative identity we want to mirror.
        //
        // IMPORTANT: only promote record.user (registered q3df account), never
        // uploaded_demo.user (the uploader - could be anyone, e.g. admin bulk
        // uploading someone else's demos).
        $canonicalUser = $seed->record?->user;
        $canonicalCountry = $seed->country
            ?? $seed->record?->country
            ?? $canonicalUser?->country
            ?? '_404';
        $canonicalName = $seed->record?->user?->name
            ?? $seed->record?->name
            ?? $seed->player_name;

        // Return MapRecord-compatible shape per entry so the frontend can reuse
        // the same <MapRecord> component (same chips: download, render, YouTube,
        // report, flag - all for free).
        $history = $cluster->map(function ($d) use ($canonicalUser, $canonicalCountry, $canonicalName, $validityFlags) {
            // Online-origin demos carry an 'm' prefix on gametype (mdf/mfs/mfc).
            // record_id alone is wrong: a legit mdf demo with no main-record
            // assignment still came from online play and should show ONLINE.
            $isOnline = $d->gametype && str_starts_with($d->gametype, 'm');
            // Demos attached to an MDD record (record_id set) surface inside
            // the online history drawer with a "Verified" chip so the user
            // can tell which attempt corresponds to the official leaderboard.
            // A validity flag wins over all of it, same order DemosTopService
            // uses, because that is what MapRecord turns into the red chip.
            $verificationType = $validityFlags[$d->id]
                ?? ($d->record_id
                    ? 'verified'
                    : ($isOnline ? 'ONLINE' : 'OFFLINE'));

            return [
                // Identity
                'id' => $d->id,
                'demo_id' => $d->id,
                'record_id' => $d->record_id,

                // Times & display
                'time' => (int) $d->time_ms,
                'time_ms' => (int) $d->time_ms,
                'date_set' => $d->record_date ?? $d->created_at,
                'player_name' => $d->player_name,
                'name' => $canonicalName,
                'country' => $canonicalCountry,

                // Source / verification
                'is_online' => $isOnline,
                'verification_type' => $verificationType,
                'rank' => null, // no ranking within history view

                // Relations MapRecord needs for chip rendering
                // user inherited from canonical (fastest) demo
                'user' => $canonicalUser,
                'demo' => $d,
                'uploaded_demos' => [],
                'rendered_videos' => $d->renderedVideo ? [$d->renderedVideo] : [],

                // Q3df login extras (for debugging / signal display)
                'q3df_login_name' => $d->q3df_login_name,
                'q3df_login_name_colored' => $d->q3df_login_name_colored,
            ];
        })->values();

        return response()->json([
            'history' => $history,
            'signals' => $maxSignals,
            'seed_demo_id' => (int) $seed->id,
        ]);
    }

    /**
     * Time history for a main record that has no attached demo of its own.
     * Resolves demos via approved aliases against the profile key
     * ("user:<id>" or "mdd:<id>") and returns every online cluster member.
     */
    private function timeHistoryForProfile(string $mapname, string $physics, string $profileKey, ?string $fastcap = null)
    {
        $user = null;
        if (str_starts_with($profileKey, 'user:')) {
            $user = \App\Models\User::find((int) substr($profileKey, 5));
            if (!$user) {
                return response()->json(['history' => [], 'signals' => 0]);
            }
        } elseif (!str_starts_with($profileKey, 'mdd:')) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // A main record is a run or a fastcap, never freestyle, so the mode the
        // caller is looking at is whichever one the fastcap number says.
        $demos = UploadedDemo::where('map_name', $mapname)
            ->where(fn ($q) => $this->scopeDemoPhysics($q, $physics, $fastcap))
            ->where(fn ($q) => $this->scopeDemoFamily($q, $fastcap !== null ? 'fastcap' : 'run'))
            ->with(['renderedVideo', 'user', 'record.user'])
            ->get();

        // Drop community-flagged demos first (same policy as demo-seeded path).
        $demoIds = $demos->pluck('id')->all();
        $recordIds = $demos->pluck('record_id')->filter()->all();
        $flaggedSet = array_flip($this->communityFlaggedDemoIds($demoIds, $recordIds));
        $demos = $demos->reject(fn ($d) => isset($flaggedSet[$d->id]))->values();
        $validityFlags = $this->validityFlagsByDemo($demoIds);

        // Priority profile keys = profiles that own a main record on this
        // map+physics. Needed so the resolver's ambiguous-plain tiebreaker
        // kicks in and demos whose plain alias is claimed by multiple
        // profiles still attribute to the one with a record on the map.
        //
        // Reads the gametype the caller is actually looking at. It used to say
        // run_ unconditionally, so on a fastcap view the tiebreaker consulted
        // the run records instead - and the record time below would have been
        // read off the wrong run entirely.
        $gametype = self::recordGametype($physics, $fastcap);
        $mddTimes = $this->mddTimesByProfileKey($mapname, $gametype);
        $priorityProfileKeys = array_keys($mddTimes);

        // This row IS the record, so its drawer holds the attempts at or
        // slower than it. Anything faster belongs to the online Demos Top row
        // above, which is only ever created for a demo that beats the record.
        // Both drawers used to list the whole cluster, which is how the same
        // demo appeared twice on one page and how a faster time ended up
        // nested under a slower one.
        $recordTime = $mddTimes[$profileKey] ?? null;

        // Resolve every remaining demo and keep only those that map to
        // this profile AND are online (main record context is always online).
        $resolver = new DemoProfileResolver();
        $matched = $demos->filter(function ($d) use ($resolver, $profileKey, $priorityProfileKeys, $recordTime) {
            $isOnline = $d->gametype && str_starts_with($d->gametype, 'm');
            if (!$isOnline) return false;
            if ($recordTime !== null && (int) $d->time_ms < $recordTime) return false;
            return $resolver->resolve($d, $priorityProfileKeys) === $profileKey;
        })
        ->sortBy('time_ms')
        ->values();

        if ($matched->isEmpty()) {
            return response()->json(['history' => [], 'signals' => 0]);
        }

        // Canonical identity: registered user wins; otherwise (unclaimed
        // mdd_id profile) fall back to whichever Record's name represents
        // the profile on this map.
        $canonicalUser = $user;
        if (!$canonicalUser) {
            $mddId = (int) substr($profileKey, 4);
            $rec = \App\Models\Record::where('mapname', $mapname)
                ->where('gametype', $gametype)
                ->where('mdd_id', $mddId)
                ->first(['name', 'country']);
            $canonicalCountry = $rec->country ?? '_404';
            $canonicalName = $rec?->name ?? 'Unknown';
        } else {
            $canonicalCountry = $user->country ?? '_404';
            $canonicalName = $user->name;
        }

        $history = $matched->map(function ($d) use ($canonicalUser, $canonicalCountry, $canonicalName, $validityFlags) {
            $verificationType = $validityFlags[$d->id] ?? ($d->record_id ? 'verified' : 'ONLINE');
            return [
                'id' => $d->id,
                'demo_id' => $d->id,
                'record_id' => $d->record_id,
                'time' => (int) $d->time_ms,
                'time_ms' => (int) $d->time_ms,
                'date_set' => $d->record_date ?? $d->created_at,
                'player_name' => $d->player_name,
                'name' => $canonicalName,
                'country' => $canonicalCountry,
                'is_online' => true,
                'verification_type' => $verificationType,
                'rank' => null,
                'user' => $canonicalUser,
                'demo' => $d,
                'uploaded_demos' => [],
                'rendered_videos' => $d->renderedVideo ? [$d->renderedVideo] : [],
                'q3df_login_name' => $d->q3df_login_name,
                'q3df_login_name_colored' => $d->q3df_login_name_colored,
            ];
        })->values();

        return response()->json([
            'history' => $history,
            'signals' => 0, // signal strength not meaningful for user-keyed cluster
            'seed_demo_id' => null,
        ]);
    }

    public function random(Request $request) {
        $mapFilters = (new MapFilters())->filter($request);
        $maps = $mapFilters['query'];

        $map = $maps->reorder()->inRandomOrder()->first();

        if (!$map || !$map->name) {
            return response()->json(['error' => 'No maps match the current filters'], 404);
        }

        return response()->json(['name' => $map->name]);
    }

    /**
     * API endpoint: return MDD profiles for filter dropdowns (lazy-loaded)
     */
    public function profiles()
    {
        $profiles = MddProfile::orderBy('id', 'DESC')
            ->with('user:id,name,plain_name,country')
            ->get(['id', 'user_id', 'name', 'country', 'plain_name']);

        return response()->json($profiles);
    }

    public function map(Request $request, $mapname) {
        $column = $request->input('sort', 'time');
        $order = $request->input('order', 'ASC');

        $map = Map::where('name', $mapname)->with(['tags.parent:id,name,display_name', 'tags.children:id,name,display_name,parent_tag_id'])->firstOrFail();

        // Auto-detect most populated gametype if not specified
        $gametype = $request->input('gametype');
        if (!$gametype) {
            // Get record counts per gametype
            $gametypeCounts = Record::where('mapname', $map->name)
                ->selectRaw('SUBSTRING_INDEX(gametype, "_", 1) as base_gametype, COUNT(*) as count')
                ->groupBy('base_gametype')
                ->orderBy('count', 'DESC')
                ->first();

            $gametype = $gametypeCounts ? $gametypeCounts->base_gametype : 'run';
        }

        $cpmGametype = $gametype . '_cpm';
        $vq3Gametype = $gametype . '_vq3';


        if ($request->user() && $request->user()->mdd_id) {
            $my_cpm_record = Record::where('mapname', $mapname)->where('mdd_id', $request->user()->mdd_id)->where('gametype', $cpmGametype)->with('user')->first();
            $my_vq3_record = Record::where('mapname', $mapname)->where('mdd_id', $request->user()->mdd_id)->where('gametype', $vq3Gametype)->with('user')->first();
        } else {
            $my_cpm_record = null;
            $my_vq3_record = null;
        }

        if (! in_array($column, ['date_set', 'time'])) {
            $column = 'date_set';
        }

        if (! in_array($order, ['DESC', 'ASC'])) {
            $order = 'DESC';
        }

        // Get record counts per gametype for UI display
        $gametypeStats = Record::where('mapname', $map->name)
            ->selectRaw('SUBSTRING_INDEX(gametype, "_", 1) as base_gametype, COUNT(*) as total')
            ->groupBy('base_gametype')
            ->get()
            ->keyBy('base_gametype')
            ->map(fn($item) => $item->total)
            ->toArray();

        $cpmRecords = Record::where('mapname', $map->name);

        $cpmRecords = $cpmRecords->where('gametype', $cpmGametype);

        // Get all CPM records sorted by time to calculate proper time-based ranks
        $allCpmRecordsByTime = Record::where('mapname', $map->name)
            ->where('gametype', $cpmGametype)
            ->orderBy('time', 'ASC')
            ->orderBy('date_set', 'ASC')
            ->pluck('id')
            ->toArray();

        $cpmRecords = $cpmRecords->with(['user', 'uploadedDemos', 'renderedVideos' => fn($q) => $q->visible()->latest()])->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'cpmPage')->withQueryString();

        // Attach community flags before ranking
        $this->attachCommunityFlags($cpmRecords);

        // Get IDs of records with approved community flags (excluded from ranking)
        $cpmFlaggedIds = $cpmRecords->getCollection()->filter(fn ($r) => !empty($r->approved_flags))->pluck('id')->toArray();
        $allCpmFlaggedIds = RecordFlag::where('status', 'approved')->whereNotNull('record_id')
            ->whereIn('record_id', $allCpmRecordsByTime)->pluck('record_id')->unique()->toArray();
        // Also check flags via demos. Group by record_id so multiple demos
        // pointing to the same record are all considered (defensive - the
        // 1-record-1-demo guard in DemoAutoAssigner enforces uniqueness for
        // new assignments, but legacy duplicates may still exist).
        $cpmDemoFlaggedRecordIds = [];
        $cpmRecordDemoIdMap = UploadedDemo::whereIn('record_id', $allCpmRecordsByTime)
            ->whereNotNull('record_id')
            ->get(['id', 'record_id'])
            ->groupBy('record_id')
            ->map(fn ($demos) => $demos->pluck('id')->all())
            ->toArray();
        if (!empty($cpmRecordDemoIdMap)) {
            $allCpmDemoIds = collect($cpmRecordDemoIdMap)->flatten()->unique()->values()->all();
            $flaggedDemoIds = RecordFlag::where('status', 'approved')->whereIn('demo_id', $allCpmDemoIds)->pluck('demo_id')->unique()->toArray();
            $cpmDemoFlaggedRecordIds = collect($cpmRecordDemoIdMap)
                ->filter(fn ($demoIds) => !empty(array_intersect($demoIds, $flaggedDemoIds)))
                ->keys()
                ->toArray();
        }
        $allCpmFlaggedIds = array_unique(array_merge($allCpmFlaggedIds, $cpmDemoFlaggedRecordIds));

        // Calculate ranks excluding flagged records
        $cpmRankMap = [];
        $rank = 0;
        foreach ($allCpmRecordsByTime as $id) {
            if (in_array($id, $allCpmFlaggedIds)) {
                $cpmRankMap[$id] = null;
            } else {
                $rank++;
                $cpmRankMap[$id] = $rank;
            }
        }
        $cpmRecords->getCollection()->transform(function ($record) use ($cpmRankMap) {
            $record->rank = $cpmRankMap[$record->id] ?? null;
            return $record;
        });

        $vq3Records = Record::where('mapname', $map->name);

        $vq3Records = $vq3Records->where('gametype', $vq3Gametype);

        // Get all VQ3 records sorted by time to calculate proper time-based ranks
        $allVq3RecordsByTime = Record::where('mapname', $map->name)
            ->where('gametype', $vq3Gametype)
            ->orderBy('time', 'ASC')
            ->orderBy('date_set', 'ASC')
            ->pluck('id')
            ->toArray();

        $vq3Records = $vq3Records->with(['user', 'uploadedDemos', 'renderedVideos' => fn($q) => $q->visible()->latest()])->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'vq3Page')->withQueryString();

        // Attach community flags before ranking
        $this->attachCommunityFlags($vq3Records);

        $allVq3FlaggedIds = RecordFlag::where('status', 'approved')->whereNotNull('record_id')
            ->whereIn('record_id', $allVq3RecordsByTime)->pluck('record_id')->unique()->toArray();
        $vq3RecordDemoIdMap = UploadedDemo::whereIn('record_id', $allVq3RecordsByTime)
            ->whereNotNull('record_id')
            ->get(['id', 'record_id'])
            ->groupBy('record_id')
            ->map(fn ($demos) => $demos->pluck('id')->all())
            ->toArray();
        if (!empty($vq3RecordDemoIdMap)) {
            $allVq3DemoIds = collect($vq3RecordDemoIdMap)->flatten()->unique()->values()->all();
            $flaggedDemoIds = RecordFlag::where('status', 'approved')->whereIn('demo_id', $allVq3DemoIds)->pluck('demo_id')->unique()->toArray();
            $vq3DemoFlaggedRecordIds = collect($vq3RecordDemoIdMap)
                ->filter(fn ($demoIds) => !empty(array_intersect($demoIds, $flaggedDemoIds)))
                ->keys()
                ->toArray();
            $allVq3FlaggedIds = array_unique(array_merge($allVq3FlaggedIds, $vq3DemoFlaggedRecordIds));
        }

        $vq3RankMap = [];
        $rank = 0;
        foreach ($allVq3RecordsByTime as $id) {
            if (in_array($id, $allVq3FlaggedIds)) {
                $vq3RankMap[$id] = null;
            } else {
                $rank++;
                $vq3RankMap[$id] = $rank;
            }
        }
        $vq3Records->getCollection()->transform(function ($record) use ($vq3RankMap) {
            $record->rank = $vq3RankMap[$record->id] ?? null;
            return $record;
        });

        $userDefaultOldtop = $request->user()?->default_show_oldtop ? 'true' : 'false';
        $showOldtop = $request->has('showOldtop') ? $request->input('showOldtop') : $userDefaultOldtop;

        if ($showOldtop === 'true') {
             // old top
            $cpmOldRecords = OldtopRecord::where('mapname', $map->name);

            $cpmOldRecords = $cpmOldRecords->where('gametype', $cpmGametype);

            $cpmOldRecords = $cpmOldRecords->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'cpmPage')->withQueryString();

            $vq3OldRecords = OldtopRecord::where('mapname', $map->name);

            $vq3OldRecords = $vq3OldRecords->where('gametype', $vq3Gametype);

            $vq3OldRecords = $vq3OldRecords->orderBy($column, $order)->orderBy('date_set', 'ASC')->paginate(50, ['*'], 'vq3Page')->withQueryString();
            // oldtop end
        } else {
            $cpmOldRecords = null;
            $vq3OldRecords = null;
        }

        // Offline demos
        $userDefaultOffline = $request->user()?->default_show_offline ? 'true' : 'false';
        $showOffline = $request->has('showOffline') ? $request->input('showOffline') : $userDefaultOffline;

        // Physics patterns are needed for both the unified leaderboard (when
        // showOffline is on) and the standalone Demos Top fallback, so pull
        // them out of the if-block.
        // Which fastcap the page is showing decides this, NOT the map's name.
        // Only 66 of the 1584 maps that hold fastcap records are called ctf*
        // or actf* - q3ctf1, q3wcp16 and rtctf5 are fastcap maps too - and for
        // every other one the name test fell through to CPM%, which matches
        // CPM.1 through CPM.7. That put a ctf1 demo in the ctf2 leaderboard.
        if (preg_match('/^ctf(\d)/', $gametype, $ctf)) {
            $cpmPattern = "CPM.{$ctf[1]}%";
            $vq3Pattern = "VQ3.{$ctf[1]}%";
        } else {
            $cpmPattern = 'CPM%';
            $vq3Pattern = 'VQ3%';
        }

        // When any "extras" toggle is on, replace the main records paginator
        // with a unified leaderboard that merges main + DT reps + oldtop into
        // one continuous rank 1..N, paginated 50 per page. Frontend detects
        // the replacement by seeing $xOfflineRecords/$xOldRecords as null and
        // skips its legacy merge pass.
        if ($showOffline === 'true' || $showOldtop === 'true') {
            $cpmRecords = $this->buildUnifiedLeaderboard(
                $map->name, $cpmGametype, $cpmPattern,
                $showOffline === 'true', $showOldtop === 'true',
                $column, $order, 'cpmPage'
            );
            $vq3Records = $this->buildUnifiedLeaderboard(
                $map->name, $vq3Gametype, $vq3Pattern,
                $showOffline === 'true', $showOldtop === 'true',
                $column, $order, 'vq3Page'
            );
            $cpmOfflineRecords = null;
            $vq3OfflineRecords = null;
            $cpmOldRecords = null;
            $vq3OldRecords = null;
        } else {
            $cpmOfflineRecords = null;
            $vq3OfflineRecords = null;
        }

        // Attach map scores (map_score, reltime, base_score, rank_multiplier) from
        // player_map_scores. Done AFTER the unified-leaderboard swap so the
        // replaced paginator gets scores too - otherwise demos top / oldtop
        // toggles wiped scores from the main MDD records they pushed into the
        // unified collection.
        $this->attachMapScores($cpmRecords, $map->name, 'cpm', $gametype);
        $this->attachMapScores($vq3Records, $map->name, 'vq3', $gametype);

        // How many times each player improved on this map, so a row can offer
        // its own history the way the profile does.
        $this->attachRecordHistoryCounts($cpmRecords, $map->name, $cpmGametype);
        $this->attachRecordHistoryCounts($vq3Records, $map->name, $vq3Gametype);

        // Redirect clamps must respect every source that shares the page
        // parameter - main records, oldtop, and Demos Top (grouped offline).
        // Old logic compared against $cpmRecords->lastPage() only, so a map
        // with e.g. 50 main records (1 page) and 300 Demos Top reps (6 pages)
        // would silently bounce every Demos Top page > 1 back to page 1.
        $cpmMaxPage = max(
            $cpmRecords->lastPage(),
            $cpmOldRecords ? $cpmOldRecords->lastPage() : 1,
            $cpmOfflineRecords ? $cpmOfflineRecords->lastPage() : 1,
        );
        $vq3MaxPage = max(
            $vq3Records->lastPage(),
            $vq3OldRecords ? $vq3OldRecords->lastPage() : 1,
            $vq3OfflineRecords ? $vq3OfflineRecords->lastPage() : 1,
        );

        $cpmPage = ($request->has('cpmPage')) ? min($request->cpmPage, $cpmMaxPage) : 1;
        $vq3Page = ($request->has('vq3Page')) ? min($request->vq3Page, $vq3MaxPage) : 1;

        if ($request->has('vq3Page') && $request->get('vq3Page') > $vq3MaxPage) {
            return redirect()->route('maps.map', ['vq3Page' => $vq3MaxPage, 'mapname' => $mapname, 'cpmPage' => $cpmPage]);
        }

        if ($request->has('cpmPage') && $request->get('cpmPage') > $cpmMaxPage) {
            return redirect()->route('maps.map', ['cpmPage' => $cpmMaxPage, 'mapname' => $mapname, 'vq3Page' => $vq3Page]);
        }

        // Precompute time-history cluster metadata per demo for this map.
        // Frontend shows these counts/signal strengths on the leaderboard
        // *without* having to open each drawer - mirrors what the time-history
        // endpoint computes so the numbers match before and after click.
        // Priority profile keys ("user:X" / "mdd:Y") that own a main record
        // on this map+physics. The cluster metadata uses them as a
        // tiebreaker for ambiguous plain aliases - same heuristic as
        // buildGroupedDemosTop, so the badge counts stay consistent with
        // the Demos Top rep counts.
        $cpmMainKeys = \App\Models\Record::whereIn('id', $allCpmRecordsByTime)
            ->select(['user_id', 'mdd_id'])->get()
            ->flatMap(fn ($r) => array_filter([
                $r->user_id ? 'user:' . (int) $r->user_id : null,
                $r->mdd_id ? 'mdd:' . (int) $r->mdd_id : null,
            ]))->unique()->values()->toArray();
        $vq3MainKeys = \App\Models\Record::whereIn('id', $allVq3RecordsByTime)
            ->select(['user_id', 'mdd_id'])->get()
            ->flatMap(fn ($r) => array_filter([
                $r->user_id ? 'user:' . (int) $r->user_id : null,
                $r->mdd_id ? 'mdd:' . (int) $r->mdd_id : null,
            ]))->unique()->values()->toArray();
        $viewedFastcap = preg_match('/^ctf(\d)/', $gametype, $ctfViewed) ? $ctfViewed[1] : null;
        $clusterMetaVq3 = $this->computeClusterMetadataForMap($map->name, 'vq3', $vq3MainKeys, $viewedFastcap);
        $clusterMetaCpm = $this->computeClusterMetadataForMap($map->name, 'cpm', $cpmMainKeys, $viewedFastcap);

        // Get servers currently playing this map
        $servers = \App\Models\Server::where('map', $map->name)
            ->where('online', true)
            ->where('visible', true)
            ->with('onlinePlayers')
            ->get();

        // Every online server, for the instant-play picker. Deliberately a
        // second list: the one above is servers ALREADY running this map,
        // which is the opposite of what you want when looking for somewhere
        // to load it. Same query the Play Later list uses.
        $onlineServers = \App\Models\Server::where('online', true)
            ->where('visible', true)
            ->with('onlinePlayers')
            ->get();

        // Get public maplists that include this map
        $publicMaplists = \App\Models\Maplist::whereHas('maps', function($query) use ($map) {
                $query->where('map_id', $map->id);
            })
            ->where('is_public', true)
            ->where('is_play_later', false)
            ->withCount('maps')
            ->with('user:id,name')
            ->orderBy('favorites_count', 'desc')
            ->limit(10)
            ->get();

        // Difficulty rating
        $difficultyRatings = MapDifficultyRating::where('map_id', $map->id)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
        $difficultyTotal = array_sum($difficultyRatings);
        $difficultyAvg = $difficultyTotal > 0
            ? round(array_sum(array_map(fn($r, $c) => $r * $c, array_keys($difficultyRatings), array_values($difficultyRatings))) / $difficultyTotal, 1)
            : null;
        $userDifficultyRating = $request->user()
            ? MapDifficultyRating::where('map_id', $map->id)->where('user_id', $request->user()->id)->value('rating')
            : null;

        return Inertia::render('MapView')
            ->with('map', $map)
            ->with('untimedDemos', $this->untimedDemos($map))
            ->with('cpmRecords', $cpmRecords)
            ->with('vq3Records', $vq3Records)
            ->with('my_cpm_record', $my_cpm_record)
            ->with('my_vq3_record', $my_vq3_record)
            ->with('cpmOldRecords', $cpmOldRecords)
            ->with('vq3OldRecords', $vq3OldRecords)
            ->with('cpmOfflineRecords', $cpmOfflineRecords)
            ->with('vq3OfflineRecords', $vq3OfflineRecords)
            ->with('gametypeStats', $gametypeStats)
            ->with('showOldtop', ($showOldtop === 'true'))
            ->with('showOffline', ($showOffline === 'true'))
            ->with('servers', $servers)
            ->with('onlineServers', $onlineServers)
            ->with('publicMaplists', $publicMaplists)
            ->with('clusterMetaVq3', $clusterMetaVq3)
            ->with('clusterMetaCpm', $clusterMetaCpm)
            ->with('difficultyRating', [
                'average' => $difficultyAvg,
                'total' => $difficultyTotal,
                'distribution' => $difficultyRatings,
                'user_rating' => $userDifficultyRating,
            ]);

    }

    /**
     * For every uploaded_demo on a map (given physics), return how many demos
     * end up in its virtual-player cluster after applying the same dedupe
     * rules as the time-history endpoint (exclude seed, dedupe by file_hash
     * and by time_ms keeping the oldest). Also returns the max signal
     * strength (0-3) between the seed and any other cluster member.
     *
     * Returns a map: ['<demo_id>' => ['count' => int, 'signals' => int]]
     * so the frontend can look up per-row badge data in O(1).
     */
    private function computeClusterMetadataForMap(string $mapname, string $physics, array $priorityProfileKeys = [], ?string $fastcap = null): array
    {
        // Same gametype rule as the drawer itself, or the badge would promise
        // a count the drawer then refuses to show.
        // Same mode rule as the drawer, or the badge counts freestyle demos the
        // drawer then refuses to list.
        $demos = UploadedDemo::where('map_name', $mapname)
            ->where(fn ($q) => $this->scopeDemoPhysics($q, $physics, $fastcap))
            ->where(fn ($q) => $this->scopeDemoFamily($q, $fastcap !== null ? 'fastcap' : 'run'))
            ->get(['id', 'player_name', 'q3df_login_name', 'q3df_login_name_colored',
                   'time_ms', 'gametype', 'record_date', 'record_id', 'file_hash', 'created_at']);

        if ($demos->isEmpty()) {
            return [];
        }

        // Identify flagged demos - they must not cluster with legitimate
        // attempts. Community verdicts only, exactly what the drawer refuses,
        // or the badge would again promise a count the drawer will not serve.
        $demoIds = $demos->pluck('id')->all();
        $recordIds = $demos->pluck('record_id')->filter()->all();
        $flaggedSet = array_flip($this->communityFlaggedDemoIds($demoIds, $recordIds));

        $grouper = new VirtualPlayerGrouper();

        // Union-find: compute root per demo once, reuse for all lookups.
        $n = $demos->count();
        $parent = range(0, $n - 1);
        $find = function ($i) use (&$parent) {
            while ($parent[$i] !== $i) { $parent[$i] = $parent[$parent[$i]]; $i = $parent[$i]; }
            return $i;
        };
        $union = function ($a, $b) use (&$parent, $find) {
            $ra = $find($a); $rb = $find($b);
            if ($ra !== $rb) $parent[$ra] = $rb;
        };

        // Same profile resolver as buildGroupedDemosTop so the cluster
        // metadata (which drives the TimeHistory badge counts in the main
        // records table) agrees with the Demos Top rep counts.
        $profileResolver = new DemoProfileResolver();

        // Same record times both drawers cut on, so a badge never counts a
        // demo the drawer will hand to the other row.
        $mddTimes = $this->mddTimesByProfileKey($mapname, self::recordGametype($physics, $fastcap));

        $byName = []; $byColored = []; $byPlain = []; $byUser = [];
        $demosArr = $demos->values();
        foreach ($demosArr as $i => $d) {
            if (isset($flaggedSet[$d->id])) continue;
            $name = strtolower(trim(preg_replace('/\^[0-9\[\]]/', '', $d->player_name ?? '')));
            $colored = trim($d->q3df_login_name_colored ?? '');
            $plain = strtolower(trim($d->q3df_login_name ?? ''));
            if ($name !== '') {
                if (isset($byName[$name])) $union($byName[$name], $i); else $byName[$name] = $i;
            }
            if ($colored !== '') {
                if (isset($byColored[$colored])) $union($byColored[$colored], $i); else $byColored[$colored] = $i;
            }
            if ($plain !== '') {
                if (isset($byPlain[$plain])) $union($byPlain[$plain], $i); else $byPlain[$plain] = $i;
            }
            $resolvedKey = $profileResolver->resolve($d, $priorityProfileKeys);
            if ($resolvedKey !== null) {
                if (isset($byUser[$resolvedKey])) $union($byUser[$resolvedKey], $i); else $byUser[$resolvedKey] = $i;
            }
        }

        // Group items by root. Flagged demos are skipped here as well, not only
        // in the linking above: they never join a bucket, so each one survived
        // as a cluster of its own and the singleton branch below then published
        // a profile-key badge for it. Both time-history endpoints drop flagged
        // demos outright, so that badge opened on "No matching demos found" -
        // four of the seven profile badges on 2plyr did exactly that.
        $clusters = [];
        foreach ($demosArr as $i => $d) {
            if (isset($flaggedSet[$d->id])) {
                continue;
            }

            $r = $find($i);
            $clusters[$r][] = $d;
        }

        // Count helper - same policy as /time-history: exclude the seed
        // (its own demo id) and count the rest. Distinct demo_ids with the
        // same file_hash or time_ms are intentionally kept; the drawer
        // shows every attempt that's a separate upload.
        $dedupeAndCount = function (array $subMembers, object $seed) use ($grouper) {
            if (empty($subMembers)) return [0, 0];
            $rest = array_values(array_filter($subMembers, fn ($d) => (int) $d->id !== (int) $seed->id));
            $maxSignals = 0;
            foreach ($rest as $d) {
                $s = $grouper->signalStrength($seed, $d);
                if ($s > $maxSignals) $maxSignals = $s;
            }
            return [count($rest), $maxSignals];
        };

        // Count without excluding any seed - used for user-keyed main record
        // cluster meta where the record has no demo of its own to exclude.
        $dedupeAndCountAll = function (array $subMembers) {
            return count($subMembers);
        };

        $meta = [];
        foreach ($clusters as $members) {
            if (count($members) === 1) {
                $d = $members[0];
                $meta[(string) $d->id] = ['count' => 0, 'signals' => 0];

                // A cluster of one still has a drawer worth opening, and the
                // profile-key entry below used to be skipped for it.
                //
                // One online demo, slower than its own player's MDD record on
                // this map, and belonging to nobody else's cluster: Demos Top
                // drops it on purpose (a slower online run is an attempt on
                // the way to the PB, not a separate result), the main record
                // has no demo of its own to hang a badge on, and with no
                // profile key here the row offered no way in either. The demo
                // existed, was processed, and could be reached by nothing on
                // the map page. /time-history lists it perfectly well when
                // asked by mdd_id.
                //
                // Only for a demo that is not attached to a main record - one
                // that is attached is already the row you are looking at, and
                // giving that row a badge counting its own demo would put a
                // "1" on rows that today correctly have none.
                if ($d->gametype && str_starts_with($d->gametype, 'm') && ! $d->record_id) {
                    $rk = $profileResolver->resolve($d, $priorityProfileKeys);

                    if ($rk !== null && ($meta[$rk]['count'] ?? 0) < 1) {
                        $meta[$rk] = ['count' => 1, 'signals' => 0, 'profileKey' => $rk];
                    }
                }

                continue;
            }

            // Split by is_online (gametype starts with 'm' = online origin).
            // Every demo's badge should only count members of its own type -
            // otherwise the count on a main record's row wouldn't match the
            // contents of its (online-filtered) time-history drawer.
            $onlineMembers = []; $offlineMembers = [];
            foreach ($members as $d) {
                if ($d->gametype && str_starts_with($d->gametype, 'm')) $onlineMembers[] = $d;
                else $offlineMembers[] = $d;
            }
            // Sort each subcluster by time ASC so seed = fastest of that type.
            usort($onlineMembers, fn ($a, $b) => (int) $a->time_ms - (int) $b->time_ms);
            usort($offlineMembers, fn ($a, $b) => (int) $a->time_ms - (int) $b->time_ms);

            // The record time splits the online members between two rows, so
            // it has to be known before anything is counted. Both drawers
            // apply this same cut; a badge counted without it promises rows
            // the drawer then refuses to show.
            $clusterKeyForTime = null;
            foreach ($members as $m) {
                $rk = $profileResolver->resolve($m, $priorityProfileKeys);
                if ($rk !== null) { $clusterKeyForTime = $rk; break; }
            }
            $recordTime = $clusterKeyForTime !== null ? ($mddTimes[$clusterKeyForTime] ?? null) : null;

            // What the online Demos Top row keeps: faster than the record,
            // and no faster than the row itself.
            $onlineOwnMembers = $recordTime === null ? $onlineMembers : array_values(array_filter(
                $onlineMembers,
                fn ($d) => (int) $d->time_ms < $recordTime
            ));

            [$onlineCount, $onlineSignals] = $onlineOwnMembers
                ? $dedupeAndCount($onlineOwnMembers, $onlineOwnMembers[0])
                : [0, 0];
            [$offlineCount, $offlineSignals] = $offlineMembers
                ? $dedupeAndCount($offlineMembers, $offlineMembers[0])
                : [0, 0];

            foreach ($members as $m) {
                $isOnline = $m->gametype && str_starts_with($m->gametype, 'm');
                $meta[(string) $m->id] = $isOnline
                    ? ['count' => $onlineCount, 'signals' => $onlineSignals]
                    : ['count' => $offlineCount, 'signals' => $offlineSignals];
            }

            // Emit profile-key entry so main table records without any
            // attached demo still surface a TimeHistory badge. Key can be
            // "user:<id>" (registered defrag.racing account) or "mdd:<id>"
            // (unclaimed q3df profile). Frontend falls back to
            // record.user_id → meta["user:X"] or record.mdd_id →
            // meta["mdd:Y"] when the row has no attached demo.
            //
            // Counts what the record's own drawer will list: the attempts at
            // or slower than it. The ones that beat it are counted on the
            // online row above instead.
            $clusterProfileKey = $clusterKeyForTime;
            $recordOwnMembers = $recordTime === null ? $onlineMembers : array_values(array_filter(
                $onlineMembers,
                fn ($d) => (int) $d->time_ms >= $recordTime
            ));

            if ($clusterProfileKey !== null && !empty($recordOwnMembers)) {
                $meta[$clusterProfileKey] = [
                    'count' => $dedupeAndCountAll($recordOwnMembers),
                    'signals' => $onlineSignals,
                    'profileKey' => $clusterProfileKey,
                ];
            }
        }

        return $meta;
    }

    /**
     * Build "Demos Top" paginator with server-side virtual-player grouping.
     * Thin wrapper around buildDemosTopReps - keeps the legacy signature so
     * existing callers don't change. The real work (clustering + rep
     * selection + time-history metadata) lives in buildDemosTopReps so the
     * unified leaderboard can reuse the exact same grouping.
     */
    private function buildGroupedDemosTop(string $mapName, string $physicsPattern, string $column, string $order, string $pageName, array $mainRecordIds = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $reps = $this->buildDemosTopReps($mapName, $physicsPattern, $mainRecordIds);
        return $this->paginateReps($reps, $column, $order, $pageName, 50);
    }

    /**
     * Paginate a pre-built reps collection: sort by $column, recompute rank
     * (nulling flagged items), slice to the requested page. Shared between
     * buildGroupedDemosTop (Demos-Top-only view) and buildUnifiedLeaderboard
     * (unified main+DT+oldtop view) so rank semantics stay identical.
     */
    private function paginateReps(\Illuminate\Support\Collection $reps, string $column, string $order, string $pageName, int $perPage): \Illuminate\Pagination\LengthAwarePaginator
    {
        $currentPage = (int) (request()->input($pageName, 1));
        if ($currentPage < 1) $currentPage = 1;

        if ($column === 'date_set') {
            $reps = $order === 'ASC'
                ? $reps->sortBy(fn ($r) => strtotime((string) ($r->date_set ?? '')))->values()
                : $reps->sortByDesc(fn ($r) => strtotime((string) ($r->date_set ?? '')))->values();
        } else {
            $reps = $reps->sortBy('time_ms')->values();
        }

        $rank = 0;
        $reps = $reps->map(function ($item) use (&$rank) {
            $hasFlag = !empty($item->approved_flags) ||
                (property_exists($item, 'verification_type') && $item->verification_type
                    && !in_array($item->verification_type, ['OFFLINE', 'ONLINE', 'verified']));
            $item->rank = $hasFlag ? null : ++$rank;
            return $item;
        });

        $total = $reps->count();
        $offset = ($currentPage - 1) * $perPage;
        $items = $reps->slice($offset, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Build the Demos Top representative collection (no pagination, no rank
     * assignment). Returns a flat collection of candidate objects already
     * run through union-find + MDD-time filter + online/offline subcluster
     * split. Callers layer their own sort + rank + pagination on top.
     */
    private function buildDemosTopReps(string $mapName, string $physicsPattern, array $mainRecordIds = []): \Illuminate\Support\Collection
    {
        // Clustering lives VERBATIM in DemosTopService (shared with the queue's
        // materialization job). The result is cached per (map, physics pattern,
        // record set) and invalidated by RebuildDemosTopRanksJob on any field
        // change, with a 1h TTL backstop - so the map detail reads a precomputed
        // result instead of re-running union-find on every page load. The cached
        // value is the exact same collection the live compute produces; only how
        // often it recomputes changes.
        $compute = fn () => app(\App\Services\DemosTopService::class)->buildReps($mapName, $physicsPattern, $mainRecordIds);

        // Per-map generation counter: the key embeds the current generation, so
        // RebuildDemosTopRanksJob invalidates simply by incrementing it (old keys
        // orphan and TTL out). More robust than cache tags, which don't flush
        // reliably under this Redis/Octane setup.
        $gen = (int) \Illuminate\Support\Facades\Cache::get('demostop_gen:' . $mapName, 0);
        $ids = $mainRecordIds;
        sort($ids);
        $key = 'demostop:' . $mapName . ':g' . $gen . ':' . $physicsPattern . ':' . md5(implode(',', $ids));

        return \Illuminate\Support\Facades\Cache::remember($key, 3600, $compute);
    }

    /**
     * Unified leaderboard: merge main MDD records + Demos Top reps + oldtop
     * records into one paginated list with continuous ranks 1..N.
     *
     * Shape-wise the returned paginator replaces the main `$xRecords`
     * variable when either `showOffline` or `showOldtop` is on, so the
     * frontend stops trying to merge three separate paginators by hand
     * (which broke both pagination and rank numbering).
     *
     * Scope: each SOURCE item becomes its own row (same player can appear
     * as a main record AND as a Demos Top rep). Within-source grouping for
     * Demos Top still happens - that's where the time-history drawer's
     * cluster data comes from.
     */
    private function buildUnifiedLeaderboard(
        string $mapname,
        string $gametype,
        string $physicsPattern,
        bool $includeOffline,
        bool $includeOldtop,
        string $column,
        string $order,
        string $pageName
    ): \Illuminate\Pagination\LengthAwarePaginator {
        // --- Main records (all, unpaginated) --------------------------------
        $mainRecords = Record::where('mapname', $mapname)
            ->where('gametype', $gametype)
            ->with(['user', 'uploadedDemos', 'renderedVideos' => fn ($q) => $q->visible()->latest()])
            ->get();
        $mainRecordIds = $mainRecords->pluck('id')->toArray();

        // Community flags on main records drive their rank nulling.
        // attachCommunityFlags iterates directly over $records, so a plain
        // Eloquent Collection works - no paginator wrapping needed.
        $this->attachCommunityFlags($mainRecords);

        // Compute MDD rank map across ALL main records (sorted by time
        // ASC) so each main record carries its own MDD-table rank, which
        // the unified view overwrites for the merged rank but the client
        // still exposes via `mdd_rank` for tooltips / cluster badges.
        $allMainIdsByTime = Record::where('mapname', $mapname)
            ->where('gametype', $gametype)
            ->orderBy('time', 'ASC')
            ->orderBy('date_set', 'ASC')
            ->pluck('id')
            ->toArray();
        $flaggedMainIds = RecordFlag::where('status', 'approved')->whereNotNull('record_id')
            ->whereIn('record_id', $allMainIdsByTime)->pluck('record_id')->unique()->toArray();
        $mddRankMap = [];
        $mr = 0;
        foreach ($allMainIdsByTime as $id) {
            $mddRankMap[$id] = in_array($id, $flaggedMainIds) ? null : ++$mr;
        }

        $unified = collect();

        foreach ($mainRecords as $record) {
            $record->time_ms = $record->time; // mirror so sort/rank share one field
            $record->mdd_rank = $mddRankMap[$record->id] ?? null;
            $record->source_type = 'main';
            // Don't stamp verification_type - the frontend decides
            // verification from uploaded_demos.length > 0. Forcing
            // 'verified' here made every main record light up green even
            // when no demo was attached.
            $unified->push($record);
        }

        // --- Oldtop ---------------------------------------------------------
        if ($includeOldtop) {
            $oldRecords = OldtopRecord::where('mapname', $mapname)
                ->where('gametype', $gametype)
                ->orderBy('time', 'ASC')
                ->get();
            foreach ($oldRecords as $old) {
                $old->time_ms = $old->time;
                $old->oldtop = true;
                $old->source_type = 'oldtop';
                // Same reasoning as main records - leave verification_type
                // unset so the frontend's demo-attached check decides.
                $unified->push($old);
            }
        }

        // --- Demos Top reps -------------------------------------------------
        // Same grouping logic as the stand-alone Demos Top paginator: each
        // cluster contributes up to two rows (fastest online + fastest
        // offline) with embedded time-history metadata.
        if ($includeOffline) {
            $dtReps = $this->buildDemosTopReps($mapname, $physicsPattern, $mainRecordIds);
            foreach ($dtReps as $rep) {
                if (empty($rep->source_type)) {
                    $rep->source_type = $rep->is_online ?? false ? 'dt_online' : 'dt_offline';
                }
                $unified->push($rep);
            }
        }

        return $this->paginateReps($unified, $column, $order, $pageName, 50);
    }

    /**
     * Get unassigned demos that potentially match online records for a map.
     * Returns record_id => [matching demos] for matches above 30% confidence.
     */
    public function getDemoMatches(Request $request, $mapname)
    {
        $nameMatcher = app(NameMatcher::class);

        // Get all unassigned demos for this map
        $demos = UploadedDemo::where('map_name', $mapname)
            ->whereNull('record_id')
            ->whereIn('status', ['processed', 'fallback-assigned'])
            ->whereNotNull('player_name')
            ->get(['id', 'player_name', 'time_ms', 'physics', 'gametype', 'original_filename', 'created_at']);

        if ($demos->isEmpty()) {
            return response()->json([]);
        }

        // Get all online records for this map with user info
        $records = Record::where('mapname', $mapname)
            ->with('user:id,name,plain_name,mdd_id')
            ->get(['id', 'name', 'mdd_id', 'gametype', 'time']);

        if ($records->isEmpty()) {
            return response()->json([]);
        }

        $matches = [];

        // Helper to extract physics base (CPM/VQ3) and CTF number
        $parsePhysics = function ($physics, $gametype) {
            $base = 'VQ3';
            $ctfNum = null;

            // Record gametype: "ctf1_vq3", "ctf2_cpm", "run_vq3", etc.
            if ($gametype && preg_match('/ctf(\d+)_(cpm|vq3)/i', $gametype, $m)) {
                $base = strtoupper($m[2]);
                $ctfNum = $m[1];
            } elseif ($gametype && preg_match('/run_(cpm|vq3)/i', $gametype, $m)) {
                $base = strtoupper($m[1]);
            }

            // Demo physics: "CPM", "CPM.1", "VQ3.2", etc. - takes priority
            if ($physics) {
                $parts = explode('.', strtoupper($physics));
                $base = $parts[0] ?: $base;
                if (isset($parts[1])) {
                    $ctfNum = $parts[1];
                }
            }

            return [$base, $ctfNum];
        };

        foreach ($records as $record) {
            $recordName = $record->user ? $record->user->plain_name ?? $record->user->name : $record->name;
            if (empty($recordName)) continue;

            [$recordBase, $recordCtf] = $parsePhysics(null, $record->gametype);

            foreach ($demos as $demo) {
                [$demoBase, $demoCtf] = $parsePhysics($demo->physics, $demo->gametype);

                // Physics base must match (CPM vs VQ3)
                if ($demoBase !== $recordBase) continue;

                // If both have CTF numbers, they must match
                if ($recordCtf !== null && $demoCtf !== null && $recordCtf !== $demoCtf) continue;

                // Time must match exactly - without exact time match, assignment makes no sense
                if (!$demo->time_ms || $demo->time_ms !== $record->time) continue;

                $confidence = $nameMatcher->calculateConfidence($demo->player_name, $recordName);
                if ($confidence >= 20) {
                    $matches[$record->id][] = [
                        'demo_id' => $demo->id,
                        'player_name' => $demo->player_name,
                        'record_player_name' => $recordName,
                        'confidence' => $confidence,
                        'time_ms' => $demo->time_ms,
                        'filename' => $demo->original_filename,
                    ];
                }
            }

            // Sort matches by confidence descending
            if (isset($matches[$record->id])) {
                usort($matches[$record->id], fn($a, $b) => $b['confidence'] - $a['confidence']);
            }
        }

        return response()->json($matches);
    }

    public function flagNsfw($id)
    {
        if (!auth()->check()) {
            abort(403);
        }

        $map = Map::findOrFail($id);

        if ($map->is_nsfw) {
            return response()->json(['success' => false, 'message' => 'Already flagged as NSFW.']);
        }

        $map->is_nsfw = true;
        $map->save();

        return response()->json(['success' => true, 'message' => "Map \"{$map->name}\" flagged as NSFW."]);
    }

    public function unflagNsfw($id)
    {
        if (!auth()->check()) {
            abort(403);
        }

        $map = Map::findOrFail($id);
        $map->is_nsfw = false;
        $map->save();

        return response()->json(['success' => true, 'message' => "Map \"{$map->name}\" NSFW flag removed."]);
    }

    public function rateDifficulty(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $map = Map::findOrFail($id);

        MapDifficultyRating::updateOrCreate(
            ['map_id' => $map->id, 'user_id' => $request->user()->id],
            ['rating' => $request->rating]
        );

        // Return updated stats
        $ratings = MapDifficultyRating::where('map_id', $map->id)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
        $total = array_sum($ratings);
        $avg = $total > 0
            ? round(array_sum(array_map(fn($r, $c) => $r * $c, array_keys($ratings), array_values($ratings))) / $total, 1)
            : null;

        return response()->json([
            'average' => $avg,
            'total' => $total,
            'distribution' => $ratings,
            'user_rating' => (int) $request->rating,
        ]);
    }

    /**
     * Attach approved community flags to paginated records
     */
    private function attachCommunityFlags($records)
    {
        if (!$records) return;

        $recordIds = [];
        $demoIds = [];

        foreach ($records as $record) {
            $recordIds[] = $record->id;
            if ($record->uploadedDemos) {
                foreach ($record->uploadedDemos as $demo) {
                    $demoIds[] = $demo->id;
                }
            }
        }

        if (empty($recordIds) && empty($demoIds)) return;

        $flags = RecordFlag::where('status', 'approved')
            ->where(function ($q) use ($recordIds, $demoIds) {
                $q->whereIn('record_id', $recordIds);
                if (!empty($demoIds)) {
                    $q->orWhereIn('demo_id', $demoIds);
                }
            })
            ->get();

        // Index flags by record_id and demo_id
        $flagsByRecord = $flags->whereNotNull('record_id')->groupBy('record_id');
        $flagsByDemo = $flags->whereNotNull('demo_id')->groupBy('demo_id');

        foreach ($records as $record) {
            $recordFlags = collect();

            // Flags directly on this record
            if (isset($flagsByRecord[$record->id])) {
                $recordFlags = $recordFlags->merge($flagsByRecord[$record->id]);
            }

            // Flags on demos attached to this record
            if ($record->uploadedDemos) {
                foreach ($record->uploadedDemos as $demo) {
                    if (isset($flagsByDemo[$demo->id])) {
                        $recordFlags = $recordFlags->merge($flagsByDemo[$demo->id]);
                    }
                }
            }

            // Deduplicate by flag_type (keep the one with highest count)
            $record->approved_flags = $recordFlags->groupBy('flag_type')->map(function ($group) {
                return $group->sortByDesc('flag_count')->first();
            })->values()->toArray();
        }
    }

    /**
     * The number of earlier times each player on this leaderboard set here -
     * the scrape moves a beaten time into record_histories every time they
     * improve. One grouped query for the page, and the row asks for the
     * times themselves only when somebody opens it.
     */
    private function attachRecordHistoryCounts($records, string $mapname, string $gametype): void
    {
        if (!$records || $records->isEmpty()) return;

        // Unified-leaderboard mode mixes plain stdClass rows in, and those
        // carry no mdd_id; they simply get no count.
        $mddIds = $records->getCollection()
            ->map(fn ($r) => $r->mdd_id ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        if (empty($mddIds)) return;

        $counts = RecordHistory::where('mapname', $mapname)
            ->where('gametype', $gametype)
            ->whereIn('mdd_id', $mddIds)
            ->groupBy('mdd_id')
            ->selectRaw('mdd_id, COUNT(*) AS n')
            ->pluck('n', 'mdd_id');

        $records->getCollection()->transform(function ($record) use ($counts) {
            $mddId = $record->mdd_id ?? null;
            $record->history_count = $mddId ? (int) ($counts[$mddId] ?? 0) : 0;
            return $record;
        });
    }

    private function attachMapScores($records, string $mapname, string $physics, string $mode): void
    {
        if (!$records || $records->isEmpty()) return;

        // Unified-leaderboard mode pushes plain stdClass DT reps (no mdd_id property)
        // alongside Eloquent Record models. PHP 8.3 throws on undefined-property
        // access, so coerce to null instead and skip score attachment for reps
        // that have no mdd_id.
        $mddIds = $records->getCollection()
            ->map(fn ($r) => $r->mdd_id ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        if (empty($mddIds)) return;

        $scores = PlayerMapScore::where('mapname', $mapname)
            ->where('physics', $physics)
            ->where('mode', $mode)
            ->whereIn('mdd_id', $mddIds)
            ->get()
            ->keyBy('mdd_id');

        $records->getCollection()->transform(function ($record) use ($scores) {
            $mddId = $record->mdd_id ?? null;
            $score = $mddId ? $scores->get($mddId) : null;
            $record->map_score = $score ? round($score->map_score, 2) : null;
            $record->reltime = $score ? round($score->reltime, 4) : null;
            $record->multiplier = $score ? round($score->multiplier, 4) : null;
            $record->rank_multiplier = $score ? round($score->rank_multiplier ?? 1, 4) : null;
            // base_score = map_score / (map_mult * rank_mult) - invariant of map/rank,
            // so the tooltip can show the algorithm breakdown: base × rank × map = score
            if ($score) {
                $mapMult = $score->multiplier ?? 1;
                $rankMult = $score->rank_multiplier ?? 1;
                $divisor = $mapMult * $rankMult;
                $record->base_score = $divisor > 0
                    ? round($score->map_score / $divisor, 2)
                    : round($score->map_score, 2);
            } else {
                $record->base_score = null;
            }
            $record->is_outlier = $score ? $score->is_outlier : false;
            return $record;
        });
    }
}
