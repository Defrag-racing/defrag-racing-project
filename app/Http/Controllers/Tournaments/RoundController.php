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

use Carbon\Carbon;

class RoundController extends Controller {
    public function index (Tournament $tournament, Request $request) {
        $rounds = Round::where('tournament_id', $tournament->id)
                ->where('start_date', '<=', Carbon::now())
                ->orderBy('start_date', 'DESC')
                ->with('maps')
                ->with(['comments' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->with(['vq3_demos' => function ($query) use($request) {
                    $query->where('user_id', $request->user()->id);
                }])
                ->with(['cpm_demos' => function ($query) use($request) {
                    $query->where('user_id', $request->user()->id);
                }])
                ->get();

        return Inertia::render('Tournaments/Tournament/Rounds')
                ->with('tournament', $tournament)
                ->with('rounds', $rounds);
    }

    public function comment(Tournament $tournament, Round $round, Request $request) {
        $round->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment
        ]);

        return redirect()->route('tournaments.rounds.index', $tournament->id);
    }

    public function submit(Tournament $tournament, Round $round, Request $request) {
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

            $demoValidator->validate();

            $demoValidator->validate_maps($round);

            $demo_data = $demoValidator->get_data();
            
        } catch(\Exception $e) {
            \Log::error($e->getMessage());

            return back()->withDanger("Demo validation failed");
        }

        if ($demo_data == null) {
            return back()->withDanger('Demo is invalid');
        }

        if ($demo_data['physics'] == 'vq3') {
            $existingDemo = Demo::where('user_id', $request->user()->id)
                    ->where('round_id', $round->id)
                    ->where('physics', 'vq3')
                    ->count();

            if ($existingDemo == 3) {
                return back()->withDanger('You have already submitted 3 VQ3 demos for this round, delete one to submit a new one');
            }
        } else {
            $existingDemo = Demo::where('user_id', $request->user()->id)
                    ->where('round_id', $round->id)
                    ->where('physics', 'cpm')
                    ->count();

            if ($existingDemo == 3) {
                return back()->withDanger('You have already submitted 3 CPM demos for this round, delete one to submit a new one');
            }
        }

        $newDemo = new Demo();

        $newDemo->user_id = $request->user()->id;
        $newDemo->round_id = $round->id;

        $newDemo->file = $file;
        $newDemo->filename = $originalFilename;

        $newDemo->time = $demo_data['time'];
        $newDemo->physics = $demo_data['physics'];

        $newDemo->rank = 0;
        $newDemo->points = 0;

        $newDemo->save();



        return back()->withSuccess('Demo uploaded successfully');
    }
}
