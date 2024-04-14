<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;

use App\Models\Round;
use App\Models\Demo;

use App\External\DemoValidator;
use App\Models\Team;

use Carbon\Carbon;

class RoundController extends Controller {
    public function index (Tournament $tournament, Request $request) {
        $rounds = Round::where('tournament_id', $tournament->id)
                ->where('start_date', '<=', Carbon::now())
                ->orderBy('start_date', 'DESC')
                ->with('maps')
                ->with(['comments' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }]);

        if ($request->user()) {
            $rounds = $rounds->with(['vq3_demos' => function ($query) use($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->with(['cpm_demos' => function ($query) use($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }
                
        $rounds = $rounds->with('vq3_results.user')
            ->with('cpm_results.user')
            ->get();

        foreach($rounds as $round) {
            $round->active = $round->start_date < Carbon::now() && $round->end_date > Carbon::now();
            $round->upcoming = $round->start_date > Carbon::now();
            $round->seconds_till_finish = Carbon::parse($round->end_date)->diffInSeconds(Carbon::now());
            $round->seconds_till_start = Carbon::now()->diffInSeconds(Carbon::parse($round->start_date));
        }

        return Inertia::render('Tournaments/Tournament/Rounds')
                ->with('tournament', $tournament)
                ->with('rounds', $rounds);
    }

    public function clans (Tournament $tournament, Request $request) {
        $rounds = Round::where('tournament_id', $tournament->id)
                ->where('start_date', '<=', Carbon::now())
                ->orderBy('start_date', 'DESC')
                ->with('maps')
                ->with(['comments' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }]);

        if ($request->user()) {
            $rounds = $rounds->with(['vq3_demos' => function ($query) use($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->with(['cpm_demos' => function ($query) use($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        } 

        $rounds = $rounds->with('vq3_results.user.clan')
        ->with('cpm_results.user.clan')
        ->get();
    
        foreach($rounds as $round) {
            $vq3_results = $round->vq3_results;
            $cpm_results = $round->cpm_results;

            $clans_cpm = [];
            $clans_vq3 = [];

            foreach($vq3_results as $demo) {
                if (! $demo->counted) {
                    continue;
                }

                if (! $demo->user->clan) {
                    continue;
                }

                if (! isset($clans_vq3[$demo->user->clan->id])) {
                    $clans_vq3[$demo->user->clan->id] = [
                        'clan' => $demo->user->clan,
                        'points' => 0,
                        'rank' => 0,
                        'number' => 0
                    ];
                }

                $clans_vq3[$demo->user->clan->id]['points'] += $demo->points;
                $clans_vq3[$demo->user->clan->id]['rank'] += $demo->rank;
                $clans_vq3[$demo->user->clan->id]['number']++;
            }

            foreach($cpm_results as $demo) {
                if (! $demo->counted) {
                    continue;
                }

                if (! $demo->user->clan) {
                    continue;
                }

                if (! isset($clans_cpm[$demo->user->clan->id])) {
                    $clans_cpm[$demo->user->clan->id] = [
                        'clan' => $demo->user->clan,
                        'points' => 0,
                        'rank' => 0,
                        'number' => 0
                    ];
                }

                $clans_cpm[$demo->user->clan->id]['points'] += $demo->points;
                $clans_cpm[$demo->user->clan->id]['rank'] += $demo->rank;
                $clans_cpm[$demo->user->clan->id]['number']++;
            }

            usort($clans_vq3, function($a, $b) {
                if ($a['points'] == $b['points']) {
                    return $a['rank'] - $b['rank'];
                }
    
                return $b['points'] - $a['points'];
            });
    
            usort($clans_cpm, function($a, $b) {
                if ($a['points'] == $b['points']) {
                    return $a['rank'] - $b['rank'];
                }
    
                return $b['points'] - $a['points'];
            });

            $round->clans_vq3 = $clans_vq3;
            $round->clans_cpm = $clans_cpm;
        }

        return Inertia::render('Tournaments/Tournament/RoundsClans')
                ->with('tournament', $tournament)
                ->with('rounds', $rounds);
    }

    public function teams (Tournament $tournament, Request $request) {
        $rounds = Round::where('tournament_id', $tournament->id)
                ->where('start_date', '<=', Carbon::now())
                ->orderBy('start_date', 'DESC')
                ->with('maps')
                ->with(['comments' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }]);

        if ($request->user()) {
            $rounds = $rounds->with(['vq3_demos' => function ($query) use($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->with(['cpm_demos' => function ($query) use($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }
                

        $rounds = $rounds->with('vq3_results.user')
        ->with('cpm_results.user')
        ->get();

        $teams = Team::where('tournament_id', $tournament->id)
                ->whereNotNull('vq3_player_id')
                ->whereNotNull('cpm_player_id')
                ->with('vq3Player')
                ->with('cpmPlayer')
                ->get();
    
        foreach($rounds as $round) {
            foreach($teams as $team) {
                $vq3_results = $round->vq3_results->where('user_id', $team->vq3_player_id)->where('counted', true)->first();
                $cpm_results = $round->cpm_results->where('user_id', $team->cpm_player_id)->where('counted', true)->first();

                if ($vq3_results) {
                    if (! isset($team->vq3_points)) {
                        $team->vq3_points = 0;
                        $team->vq3_rank = 0;
                        $team->vq3_number = 0;
                    }

                    $team->vq3_points += $vq3_results->points;
                    $team->vq3_rank += $vq3_results->rank;
                    $team->vq3_number++;
                }

                if ($cpm_results) {
                    if (! isset($team->cpm_points)) {
                        $team->cpm_points = 0;
                        $team->cpm_rank = 0;
                        $team->cpm_number = 0;
                    }

                    $team->cpm_points += $cpm_results->points;
                    $team->cpm_rank += $cpm_results->rank;
                    $team->cpm_number++;
                }
            }
        }

        return Inertia::render('Tournaments/Tournament/RoundsTeams')
                ->with('tournament', $tournament)
                ->with('rounds', $rounds)
                ->with('teams', $teams);
    }

    public function comment(Tournament $tournament, Round $round, Request $request) {
        if (! $request->user()) {
            return back()->withDanger('You must be logged in to comment');
        }

        $round->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment
        ]);

        return redirect()->route('tournaments.rounds.index', $tournament->id);
    }

    public function submit(Tournament $tournament, Round $round, Request $request) {
        if (! $request->user()) {
            return back()->withDanger('You must be logged in to comment');
        }

        if (! $request->demo) {
            return back()->withDanger('You must upload a demo');
        }

        $demo = $request->file('demo');
        $originalFilename = $demo->getClientOriginalName();
        // check demo filetype
        
        if ($demo->getClientOriginalExtension() !== 'dm_68') {
            return back()->withDanger('Demo must be a dm_68 file');
        }

        if (! $round->published) {
            return back()->withDanger('This round has not been published yet');
        }

        if ($round->start_date > Carbon::now()) {
            return back()->withDanger('This round has not started yet');
        }

        if ($round->end_date < Carbon::now()) {
            return back()->withDanger('This round has already ended');
        }

        $random_name = \Str::random(20);
        $file = $demo->storeAs('tournaments/demos', $random_name . '.dm_68');

        $demo_data = null;
        try {
            $demoValidator = new DemoValidator($file);

            // $demoValidator->validate();

            $demoValidator->validate_maps($round);

            $demo_data = $demoValidator->get_data();
            
        } catch(\Exception $e) {
            \Log::error($e->getMessage());

            return back()->withDanger("Demo validation failed: " . $e->getMessage());
        }

        if ($demo_data == null) {
            return back()->withDanger('Demo is invalid');
        }

        $newDemo = new Demo();

        $newDemo->user_id = $request->user()->id;
        $newDemo->round_id = $round->id;

        $newDemo->file = $file;
        $newDemo->filename = $originalFilename;

        $newDemo->time = $demo_data['time'];
        $newDemo->physics = $demo_data['physics'];

        $newDemo->counted = ! ($tournament->organizers()->where('user_id', $request->user()->id)->exists());

        $newDemo->rank = 0;
        $newDemo->points = 0;

        $newDemo->save();

        $request->user()->check_demos($round->id);

        return back()->withSuccess('Demo uploaded successfully');
    }

    public function delete(Tournament $tournament, Demo $demo, Request $request) {
        if ($demo->user_id != $request->user()->id) {
            return back()->withDanger('You can only delete your own demos');
        }

        $request->user()->check_demos($round->id);

        $demo->delete();
    }
}
