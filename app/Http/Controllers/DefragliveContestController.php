<?php

namespace App\Http\Controllers;

use App\Models\DefragliveContest;
use App\Models\DefragliveWatchExclusion;
use App\Models\DefragliveWatchSession;
use App\Services\DefragliveWatchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public DefragLive watch-time contest page: the live leaderboard of the most-
 * spectated players this period, the prize/countdown, the viewer's own odds,
 * and past winners. The winner is a watch-time-weighted raffle, so the page
 * shows tickets + odds, not just a ranking.
 */
class DefragliveContestController extends Controller
{
    public function index(Request $request, DefragliveWatchService $service)
    {
        $contest = DefragliveContest::current()->first();
        $open = DefragliveWatchSession::open()
            ->where('last_seen_at', '>=', now()->subSeconds(DefragliveWatchService::LIVE_WINDOW))
            ->orderByDesc('id')
            ->first();

        $leaderboard = [];
        $totalTickets = 0;
        $myEntry = null;

        if ($contest) {
            $all = $service->leaderboard($contest, null);
            $totalTickets = array_sum(array_map(
                fn ($e) => $e['tickets'] >= 1 ? $e['tickets'] : 0,
                $all
            ));

            $userId = auth()->id();
            $mddId = auth()->user()?->mdd_id;

            foreach ($all as $i => $e) {
                if (($userId && $e['user_id'] === $userId)
                    || ($mddId && $e['mdd_id'] === (int) $mddId)) {
                    $myEntry = [
                        'rank' => $i + 1,
                        'seconds' => $e['seconds'],
                        'tickets' => $e['tickets'],
                        'odds' => $totalTickets > 0 ? round($e['tickets'] / $totalTickets * 100, 1) : 0,
                        'is_current' => $this->isCurrent($e, $open),
                    ];
                    break;
                }
            }

            // Top 10 by default; "Show all" grows the limit via partial
            // reloads (same growing-limit pattern as the all-time stats), so
            // everyone can find themselves. Capped like the all-time list.
            $leaderboard = array_map(
                fn (array $entry) => $this->present($entry, $open),
                array_slice($all, 0, max(10, min(400, (int) $request->input('leaderboard_limit', 10))))
            );
        }

        return Inertia::render('DefragliveContest', [
            'contest' => $contest ? [
                'id' => $contest->id,
                'title' => $contest->title,
                'prize_amount' => $contest->prize_amount,
                'prize_currency' => $contest->prize_currency,
                // Forwarded in by previous winners; base = amount - carried.
                'carried_over_amount' => (float) $contest->carried_over_amount,
                'starts_at' => $contest->starts_at?->toIso8601String(),
                'ends_at' => $contest->ends_at?->toIso8601String(),
            ] : null,
            'leaderboard' => $leaderboard,
            'leaderboardTotal' => isset($all) ? count($all) : 0,
            'totalTickets' => $totalTickets,
            'myEntry' => $myEntry,
            // Bans issued during the ACTIVE contest window - a small factual
            // note under the leaderboard (name, reason, date). Deliberately no
            // hours/avatars (no trophy for cheating) and nothing for past
            // contests; the full history stays admin-only.
            'exclusions' => $contest
                ? DefragliveWatchExclusion::whereBetween('excluded_before', [$contest->starts_at, $contest->ends_at])
                    ->orderByDesc('excluded_before')
                    ->get()
                    ->map(fn (DefragliveWatchExclusion $x) => [
                        'name' => trim(preg_replace('/\^[0-9A-Za-z]/', '', $x->player_name)) ?: $x->name_clean,
                        'reason' => $x->reason,
                        'date' => $x->excluded_before->toDateString(),
                    ])
                : [],
            'nowWatching' => $open ? [
                'name' => $open->player_name,
                'mapname' => $open->mapname,
                'seconds' => (int) ($open->started_at?->diffInSeconds(now()) ?? $open->seconds),
                'map_thumbnail' => $open->mapname
                    ? \App\Models\Map::where('name', $open->mapname)->value('thumbnail')
                    : null,
            ] : null,
            'pastWinners' => DefragliveContest::whereNotNull('winner_name')
                ->orderByDesc('drawn_at')
                ->limit(10)
                ->get()
                ->map(fn (DefragliveContest $c) => [
                    'title' => $c->title,
                    'winner_name' => $c->winner_name,
                    'winner_user_id' => $c->winner_user_id,
                    'prize_amount' => $c->prize_amount,
                    'prize_currency' => $c->prize_currency,
                    'carried_over_amount' => (float) $c->carried_over_amount,
                    'drawn_at' => $c->drawn_at?->toDateString(),
                    'status' => $c->status,
                    'winner_seconds' => (int) $c->winner_seconds,
                    'winner_tickets' => (int) $c->winner_tickets,
                    'total_tickets' => (int) $c->total_tickets,
                    // Best-of-three picks (null before 2026-09-12: one ticket).
                    'picks' => $c->draw_picks
                        ? array_map(fn ($p) => [
                            'ticket' => (int) $p['ticket'],
                            'name' => $p['name'],
                            'tickets' => (int) $p['tickets'],
                            'winner' => (int) $p['ticket'] === (int) $c->winning_ticket,
                        ], $c->draw_picks)
                        : null,
                ]),
            'hallOfFame' => $this->hallOfFame($service),
            // Loaded on demand (Inertia lazy prop): the page requests it via a
            // partial reload when the visitor expands the all-time stats view,
            // growing watchers_limit in steps of 40 as the visitor scrolls.
            'allTimeWatchers' => Inertia::lazy(fn () => $service->allTimeLeaderboard(
                // Hard cap: the list grows 40 at a time as the visitor scrolls,
                // and past rank 400 an all-time list stops being interesting -
                // this also bounds the largest partial-reload payload.
                max(1, min(400, (int) $request->input('watchers_limit', 40)))
            )),
        ]);
    }

    /**
     * OBS stream overlay: a tiny self-contained page (transparent background,
     * no Inertia/app shell) that shows the current contest's top 3 and polls
     * overlayData() to stay live. Meant to be added as an OBS Browser Source.
     */
    public function overlay()
    {
        return response()
            ->view('defraglive.contest-overlay')
            // Never cache the shell either - OBS keeps sources for days.
            ->header('Cache-Control', 'no-store');
    }

    /** JSON feed for the overlay: top 3 + pool size, cached briefly. */
    public function overlayData(DefragliveWatchService $service)
    {
        $data = cache()->remember('defraglive:contest-overlay', 20, function () use ($service) {
            $contest = DefragliveContest::current()->first();
            if (! $contest) {
                return ['contest' => null, 'top' => []];
            }

            $all = $service->leaderboard($contest, null);
            $totalTickets = array_sum(array_map(fn ($e) => max(0, $e['tickets']), $all));

            return [
                'contest' => [
                    'title' => $contest->title,
                    'ends_at' => $contest->ends_at?->toIso8601String(),
                    'prize_amount' => (float) $contest->prize_amount,
                    'prize_currency' => $contest->prize_currency,
                    // Formatted here rather than in the overlay: that page is
                    // standalone for OBS and loads no bundle, so it cannot
                    // reach the shared formatter the rest of the site uses.
                    'prize_label' => DefragliveContest::formatPrize($contest->prize_amount, $contest->prize_currency),
                ],
                'top' => array_map(fn ($e) => [
                    'name' => $e['name'],
                    'seconds' => $e['seconds'],
                    'tickets' => $e['tickets'],
                    'odds' => $totalTickets > 0 ? round($e['tickets'] / $totalTickets * 100, 1) : 0,
                ], array_slice($all, 0, 3)),
            ];
        });

        return response()->json($data)->header('Cache-Control', 'no-store');
    }

    /**
     * All-time winners: every drawn contest grouped by winner, ranked by number
     * of wins then total prize won. A small hall of fame under the page.
     */
    private function hallOfFame(DefragliveWatchService $service): array
    {
        $won = DefragliveContest::whereNotNull('winner_name')
            ->get(['winner_user_id', 'winner_mdd_id', 'winner_name', 'prize_amount', 'prize_currency', 'winner_seconds']);

        $groups = [];
        foreach ($won as $c) {
            $key = $c->winner_user_id
                ? 'u:'.$c->winner_user_id
                : 'n:'.$service->cleanName((string) $c->winner_name);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'winner_user_id' => $c->winner_user_id,
                    'name' => $c->winner_name,
                    'wins' => 0,
                    'total' => 0.0,
                    'currency' => $c->prize_currency,
                    'seconds' => 0,
                ];
            }
            $groups[$key]['wins']++;
            $groups[$key]['total'] += (float) $c->prize_amount;
            $groups[$key]['seconds'] += (int) $c->winner_seconds;
            $groups[$key]['name'] = $c->winner_name ?: $groups[$key]['name'];
        }

        $entries = array_values($groups);
        usort($entries, fn ($a, $b) => [$b['wins'], $b['total']] <=> [$a['wins'], $a['total']]);
        $entries = array_slice($entries, 0, 10);

        $userIds = array_values(array_filter(array_column($entries, 'winner_user_id')));
        $users = $userIds
            ? \App\Models\User::whereIn('id', $userIds)
                ->get(['id', 'name', 'profile_photo_path', 'country'])
                ->keyBy('id')
            : collect();

        foreach ($entries as &$e) {
            $u = $e['winner_user_id'] ? $users->get($e['winner_user_id']) : null;
            $e['user'] = $u ? [
                'id' => $u->id,
                'profile_photo_path' => $u->profile_photo_path,
                'country' => $u->country,
            ] : null;
        }

        return $entries;
    }

    /** Shape one leaderboard entry for the page (colored name + resolved user). */
    private function present(array $e, ?DefragliveWatchSession $open = null): array
    {
        return [
            'name' => $e['name'],
            'seconds' => $e['seconds'],
            'tickets' => $e['tickets'],
            'mdd_id' => $e['mdd_id'],
            'is_current' => $this->isCurrent($e, $open),
            'user' => $e['user'] ? [
                'id' => $e['user']->id,
                'name' => $e['user']->name,
                'profile_photo_path' => $e['user']->profile_photo_path,
                'country' => $e['user']->country,
            ] : null,
        ];
    }

    private function isCurrent(array $entry, ?DefragliveWatchSession $open): bool
    {
        if (! $open) {
            return false;
        }

        if ($entry['mdd_id'] && $open->mdd_id) {
            return (int) $entry['mdd_id'] === (int) $open->mdd_id;
        }

        return $entry['name_clean'] !== null
            && $open->player_name_clean !== null
            && (string) $entry['name_clean'] === (string) $open->player_name_clean;
    }
}
