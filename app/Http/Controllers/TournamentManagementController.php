<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;
use App\Models\Record;

use App\Rules\YouTubeUrl;
use Intervention\Image\Facades\Image;

use Carbon\Carbon;

class TournamentManagementController extends Controller {
    public function manage (Tournament $tournament, Request $request) {
        $donations = $tournament->donations()->count();
        $suggestions = $tournament->suggestions()->where('done', false)->count();

        $myroles = Organizer::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $request->user()->id)
            ->pluck('role');

        return Inertia::render('Tournaments/Tournament/ManageTournament')
                ->with('tournament', $tournament)
                ->with('donations', $donations)
                ->with('suggestions', $suggestions)
                ->with('myroles', $myroles);
    }

    public function create(Request $request) {
        if (Record::where('user_id', $request->user()->id)->count() < 50) {
            return back()->withDanger('You need to have at least 50 records to create a tournament.');
        }

        return Inertia::render('Tournaments/Create');
    }

    public function store(Request $request) {
        if (Record::where('user_id', $request->user()->id)->count() < 50) {
            return back()->withDanger('You need to have at least 50 records to create a tournament.');
        }

        $request->validate([
            'name' => ['required', 'max:255', 'string'],
            'description' => ['required', 'string'],
            'rules' => ['required', 'string'],
            'photo' => ['required', 'image'],
            'has_teams' => ['required', 'boolean'],
            'prize_pool' => ['required', 'numeric'],
            'has_donations' => ['required', 'boolean'],
            'trailer' => ['nullable', new YouTubeUrl]
        ]);

        $slug = \Str::slug($request->name);

        if (Tournament::where('slug', $slug)->exists()) {
            return Inertia::render('Tournaments/Create')->with('error', 'A tournament with that name already exists');
        }

        $tournament = new Tournament;
        $tournament->name = $request->name;
        $tournament->description = $request->description;
        $tournament->rules = $request->rules;
        $tournament->slug = $slug;
        $tournament->has_teams = $request->has_teams;
        $tournament->prize_pool = $request->prize_pool;
        $tournament->has_donations = $request->has_donations;
        $tournament->creator = $request->user()->id;

        $image = $request->file('photo');
        $img = Image::make($image);

        $width = $img->width();
        $height = $img->height();

        $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
        $uploadPath = public_path('storage/tournaments');

        if ($width > 640 || $height > 360) {
            $image = Image::make($image)->fit(640, 360);
            $image->save($uploadPath . '/' . $imageName);
            $file = 'tournaments/' . $imageName;
        } else {
            $file = $image->store('tournaments', 'public');
        }

        if ($request->has('trailer')) {
            $tournament->trailer = $request->trailer;
        }

        $tournament->image = $file;

        $tournament->save();

        $organizer = new Organizer;
        $organizer->tournament_id = $tournament->id;
        $organizer->user_id = $request->user()->id;
        $organizer->role = 'admin';
        $organizer->save();

        return redirect()->route('tournaments.index');
    }

    public function edit(Tournament $tournament, Request $request) {
        $organizer = Organizer::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $request->user()->id)
            ->where('role', '!=', 'validator')
            ->exists();

        if (!$organizer) {
            return redirect()->route('tournaments.index');
        }

        return Inertia::render('Tournaments/Edit')->with('tournament', $tournament);
    }

    public function update(Tournament $tournament, Request $request) {
        $organizer = Organizer::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $request->user()->id)
            ->where('role', '!=', 'validator')
            ->exists();

        if (!$organizer) {
            return redirect()->route('tournaments.index');
        }

        $request->validate([
            'name' => ['required', 'max:255', 'string'],
            'description' => ['required', 'string'],
            'rules' => ['required', 'string'],
            'has_teams' => ['required', 'boolean'],
            'prize_pool' => ['required', 'numeric'],
            'has_donations' => ['required', 'boolean'],
            'trailer' => ['nullable', new YouTubeUrl]
        ]);

        $slug = \Str::slug($request->name);

        $tournament->name = $request->name;
        $tournament->description = $request->description;
        $tournament->rules = $request->rules;
        $tournament->slug = $slug;
        $tournament->has_teams = $request->has_teams;
        $tournament->prize_pool = $request->prize_pool;
        $tournament->has_donations = $request->has_donations;
        $tournament->creator = $request->user()->id;

        if ($request->has('photo')) {
            $image = $request->file('photo');
            $img = Image::make($image);

            $width = $img->width();
            $height = $img->height();

            $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
            $uploadPath = public_path('storage/tournaments');

            if ($width > 640 || $height > 360) {
                $image = Image::make($image)->fit(640, 360);
                $image->save($uploadPath . '/' . $imageName);
                $file = 'tournaments/' . $imageName;
            } else {
                $file = $image->store('tournaments', 'public');
            }

            $tournament->image = $file;
        }

        if ($request->has('trailer')) {
            $tournament->trailer = $request->trailer;
        }

        $tournament->save();

        return redirect()->route('tournaments.index');
    }

    public function publish (Tournament $tournament, Request $request) {
        $tournament->published = ! $tournament->published;
        $tournament->save();

        return back();
    }
}
