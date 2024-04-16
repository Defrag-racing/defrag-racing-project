<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\Round;
use App\Models\RoundMap;
use Intervention\Image\Facades\Image;

use Carbon\Carbon;

class RoundManagementController extends Controller {
    public function index (Tournament $tournament) {
        $tournament->load('rounds');

        return Inertia::render('Tournaments/Management/Rounds/Index')
                ->with('tournament', $tournament);
    }

    public function create(Tournament $tournament) {
        return Inertia::render('Tournaments/Management/Rounds/Create')
                ->with('tournament', $tournament);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'name'          =>      'required|string|max:255',
            'mapname'       =>      'required|string|max:255',
            'category'      =>      'required|string|max:255',
            'author'        =>      'required|string|max:255',
            'image'         =>      'required|image',
            'start_date'    =>      'required|date',
            'end_date'      =>      'required|date'
        ]);

        $start_date = Carbon::parse($request->start_date);
        $end_date = Carbon::parse($request->end_date);
        $now = Carbon::now();

        if ($start_date->lt($now)) {
            return redirect()->route('tournaments.rounds.manage', $tournament)->withDanger('Start date must be in the future');
        }

        if ($end_date->lt($start_date)) {
            return redirect()->route('tournaments.rounds.manage', $tournament)->withDanger('End date must be after the start date');
        }

        if (isset($request->weapons) && isset($request->weapons['include'])) {
            $weapons = implode(',', $request->weapons['include']);
        } else {
            $weapons = '';
        }

        if (isset($request->items) && isset($request->items['include'])) {
            $items = implode(',', $request->items['include']);
        } else {
            $items = '';
        }

        if (isset($request->functions) && isset($request->functions['include'])) {
            $functions = implode(',', $request->functions['include']);
        } else {
            $functions = '';
        }

        $image = $request->file('image');
        $img = Image::make($image);

        $width = $img->width();
        $height = $img->height();

        $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
        $uploadPath = public_path('storage/tournaments/rounds');

        if ($width > 640 || $height > 360) {
            $image = Image::make($image)->fit(640, 360);
            $image->save($uploadPath . '/' . $imageName);
            $file = 'tournaments/rounds/' . $imageName;
        } else {
            $file = $image->store('tournaments/rounds', 'public');
        }

        $round = $tournament->rounds()->create([
            'name'          =>      $request->name,
            'author'        =>      $request->author,
            'start_date'    =>      $start_date,
            'end_date'      =>      $end_date,
            'image'         =>      $file,
            'weapons'       =>      $weapons,
            'items'         =>      $items,
            'functions'     =>      $functions,
            'mapname'       =>      $request->mapname,
            'category'      =>      $request->category
        ]);

        return redirect()->route('tournaments.rounds.manage', $tournament);
    }

    public function edit(Tournament $tournament, Round $round) {
        return Inertia::render('Tournaments/Management/Rounds/Edit')
                ->with('tournament', $tournament)
                ->with('round', $round);
    }

    public function update(Request $request, Tournament $tournament, Round $round) {
        $request->validate([
            'name'          =>      'required|string|max:255',
            'start_date'    =>      'required|date',
            'end_date'      =>      'required|date',
            'author'        =>      'required|string|max:255',
            'mapname'       =>      'required|string|max:255',
            'category'      =>      'required|string|max:255',
        ]);

        $start_date = Carbon::parse($request->start_date);
        $end_date = Carbon::parse($request->end_date);

        if ($request->file('image')) {
            $image = $request->file('image');
            $img = Image::make($image);

            $width = $img->width();
            $height = $img->height();

            $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
            $uploadPath = public_path('storage/tournaments/rounds');

            if ($width > 640 || $height > 360) {
                $image = Image::make($image)->fit(640, 360);
                $image->save($uploadPath . '/' . $imageName);
                $file = 'tournaments/rounds/' . $imageName;
            } else {
                $file = $image->store('tournaments/rounds', 'public');
            }
        } else {
            $file = $round->image;
        }

        if (isset($request->weapons) && isset($request->weapons['include'])) {
            $weapons = implode(',', $request->weapons['include']);
        } else {
            $weapons = $round->weapons;
        }

        if (isset($request->items) && isset($request->items['include'])) {
            $items = implode(',', $request->items['include']);
        } else {
            $items = $round->items;
        }

        if (isset($request->functions) && isset($request->functions['include'])) {
            $functions = implode(',', $request->functions['include']);
        } else {
            $functions = $round->functions;
        }

        $round->update([
            'name'          =>      $request->name,
            'start_date'    =>      $start_date,
            'end_date'      =>      $end_date,
            'image'         =>      $file,
            'weapons'       =>      $weapons,
            'items'         =>      $items,
            'functions'     =>      $functions,
            'author'        =>      $request->author,
            'mapname'       =>      $request->mapname,
            'category'      =>      $request->category
        ]);

        return redirect()->route('tournaments.rounds.manage', $tournament)->withSuccess('Round edited successfully.');
    }

    public function destroy(Tournament $tournament, Round $round) {
        $round->delete();

        return redirect()->route('tournaments.rounds.manage', $tournament)->withSuccess('Round deleted successfully !');
    }

    public function publish(Tournament $tournament, Round $round) {
        if (! $round->published) {
            $roundsMaps = RoundMap::query()
                ->where('round_id', $round->id)
                ->count();

            if ($roundsMaps < 1) {
                return back()->withDanger('You must add at least one map to the round before publishing it.');
            }
        }

        $round->update([
            'published'     =>      ! $round->published
        ]);

        $message = $round->published ? 'Round published successfully !' : 'Round unpublished successfully !';

        return redirect()->route('tournaments.rounds.manage', $tournament)->withSuccess($message);
    }
}
