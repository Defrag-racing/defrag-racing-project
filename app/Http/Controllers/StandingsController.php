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


        $vq3_standings = collect([]);

        $vq3_demos = Demo::whereIn('round_id', $rounds)
            ->where('physics', 'vq3')
            ->where('rejected', false)
            ->groupBy('user_id')
            ->orderBy('points', 'desc')
            ->with('user')
            ->get();

        foreach ($vq3_demos as $demo) {
            if ($vq3_standings->has($demo->user_id)) {
                $vq3_standings[$demo->user_id]['points'] += $demo->points;
            } else {
                $vq3_standings[$demo->user_id] = [
                    'points' => $demo->points,
                    'user' => $demo->user
                ];
            }
        }

        $vq3_standings = $vq3_standings->sortBy(function (array $demo, int $key) {
            return $demo['points'];
        });

        $cpm_standings = collect([]);

        $cpm_demos = Demo::whereIn('round_id', $rounds)
            ->where('physics', 'cpm')
            ->where('rejected', false)
            ->groupBy('user_id')
            ->orderBy('points', 'desc')
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

        $cpm_standings_sorted = $cpm_standings->sortByDesc(function (array $demo, int $key) {
            return $demo['points'];
        });


        return Inertia::render('Tournaments/Tournament/Standings')
            ->with('tournament', $tournament)
            ->with('vq3_standings', $vq3_standings)
            ->with('cpm_standings', $cpm_standings);
    }
}
