<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\TournamentNew;
use App\Models\Comment;

class NewsController extends Controller {
    public function index (Tournament $tournament) {
        $news = TournamentNew::where('tournament_id', $tournament->id)
            ->orderBy('created_at', 'desc')
            ->with(['comments' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->get();

        return Inertia::render('Tournaments/Tournament/News')
                ->with('tournament', $tournament)
                ->with('news', $news);
    }

    public function comment (Request $request, Tournament $tournament, TournamentNew $new) {
        $new->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment,
        ]);

        return redirect()->route('tournaments.news.index', $tournament);
    }

    public function reply (Request $request, Tournament $tournament, Comment $comment) {
        $comment->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment,
        ]);

        return back();
    }
}
