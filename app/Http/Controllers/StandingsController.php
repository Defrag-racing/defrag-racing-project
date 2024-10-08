<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Round;
use App\Models\Demo;
use Carbon\Carbon;

class StandingsController extends Controller {
    public function index(Request $request, Tournament $tournament) {
        $rounds = Round::where('tournament_id', $tournament->id)
            ->where('published', true)
            ->where('results', true)
            ->pluck('id');

        $cpm_standings = collect([]);

        $cpm_demos = Demo::whereIn('round_id', $rounds)
            ->where('physics', 'cpm')
            ->where('rejected', false)
            ->where('best', true)
            ->where('counted', true)
            ->groupBy('user_id')
            ->with('user')
            ->get();

        foreach ($cpm_demos as $demo) {
            $first = $cpm_standings->firstWhere('id', $demo->user_id);
            if ($first) {
                $first['points'] += $demo->points;
            } else {
                $cpm_standings[] = [
                    'points'    => $demo->points,
                    'user'      => $demo->user,
                    'id'        => $demo->user_id
                ];
            }
        }

        $cpm_standings = $cpm_standings->sortByDesc('points', SORT_REGULAR);

        $vq3_standings = collect([]);

        $vq3_demos = Demo::whereIn('round_id', $rounds)
            ->where('physics', 'vq3')
            ->where('rejected', false)
            ->where('best', true)
            ->where('counted', true)
            ->groupBy('user_id')
            ->with('user')
            ->get();

        foreach ($vq3_demos as $demo) {
            $first = $vq3_standings->firstWhere('id', $demo->user_id);

            if ($first) {
                $first['points'] += $demo->points;
            } else {
                $vq3_standings[] = [
                    'points'    => $demo->points,
                    'user'      => $demo->user,
                    'id'        => $demo->user_id
                ];
            }
        }

        $vq3_standings = $vq3_standings->sortByDesc('points', SORT_REGULAR);


        return Inertia::render('Tournaments/Tournament/Standings')
            ->with('tournament', $tournament)
            ->with('vq3_standings', $vq3_standings->values())
            ->with('cpm_standings', $cpm_standings->values());
    }

    public function clans(Request $request, Tournament $tournament) {
        $results = $tournament->clanResults();

        return Inertia::render('Tournaments/Tournament/StandingsClans')
            ->with('tournament', $tournament)
            ->with('vq3_standings', $results['vq3'])
            ->with('cpm_standings', $results['cpm']);
    }

    public function teams(Request $request, Tournament $tournament) {
        $results = $tournament->teamResults();

        return Inertia::render('Tournaments/Tournament/StandingsTeams')
            ->with('tournament', $tournament)
            ->with('standings', $results);
    }
}
