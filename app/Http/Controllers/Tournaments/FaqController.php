<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\TournamentFaq as Faq;

use Carbon\Carbon;

class FaqController extends Controller {
    public function index (Tournament $tournament) {
        $tournament->load('faqs');

        return Inertia::render('Tournaments/Management/Faqs/Faqs')
                ->with('tournament', $tournament);
    }

    public function create(Tournament $tournament) {
        return Inertia::render('Tournaments/Management/Faqs/FaqCreate')
                ->with('tournament', $tournament);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'question'  =>  'required|string|max:255',
            'answer' => 'required|string',
        ]);

        $faq = $tournament->faqs()->create([
            'question' => $request->question,
            'answer' => $request->answer,
        ]);

        return redirect()->route('tournaments.faqs.manage', $tournament);
    }

    public function edit(Tournament $tournament, Faq $faq) {
        return Inertia::render('Tournaments/Management/Faqs/FaqEdit')
                ->with('tournament', $tournament)
                ->with('faq', $faq);
    }

    public function update(Request $request, Tournament $tournament, Faq $faq) {
        $request->validate([
            'question'  =>  'required|string|max:255',
            'answer' => 'required|string',
        ]);

        $faq->update([
            'question' => $request->question,
            'answer' => $request->answer,
        ]);

        return redirect()->route('tournaments.faqs.manage', $tournament);
    }

    public function destroy(Tournament $tournament, Faq $faq) {
        $faq->delete();

        return redirect()->route('tournaments.faqs.manage', $tournament);
    }
}
