<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\TournamentNew;

use Carbon\Carbon;

class NewsManagementController extends Controller {
    public function index (Tournament $tournament) {
        $news = TournamentNew::where('tournament_id', $tournament->id)->get();

        return Inertia::render('Tournaments/Management/News/Index')
                ->with('tournament', $tournament)
                ->with('news', $news);
    }

    public function create(Tournament $tournament) {
        return Inertia::render('Tournaments/Management/News/Create')
                ->with('tournament', $tournament);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'title'     => 'required|string|max:255',
            'content'   => 'required|string'
        ]);

        $news = new TournamentNew();
        $news->title = $request->title;
        $news->content = $request->content;

        if ($request->has('image') && $request->file('image') != null){
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            $news->image = $request->file('image')->store('news', 'public');
        }

        $news->tournament_id = $tournament->id;
        $news->save();

        return redirect()->route('tournaments.news.manage', $tournament);
    }

    public function edit(Tournament $tournament, TournamentNew $news) {
        return Inertia::render('Tournaments/Management/News/Edit')
                ->with('tournament', $tournament)
                ->with('news', $news);
    }

    public function update(Request $request, Tournament $tournament, TournamentNew $news) {
        $request->validate([
            'title'     => 'required|string|max:255',
            'content'   => 'required|string'
        ]);

        $news->title = $request->title;
        $news->content = $request->content;

        if ($request->has('image') && $request->file('image') != null){
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            $news->image = $request->file('image')->store('news', 'public');
        }

        $news->save();

        return redirect()->route('tournaments.news.manage', $tournament);
    }

    public function destroy(Tournament $tournament, TournamentNew $news) {
        $news->delete();

        return redirect()->route('tournaments.news.manage', $tournament);
    }
}
