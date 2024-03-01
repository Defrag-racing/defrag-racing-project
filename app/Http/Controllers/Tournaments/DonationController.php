<?php

namespace App\Http\Controllers\Tournaments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tournament;
use App\Models\Organizer;

use App\Rules\YouTubeUrl;

use App\Http\Controllers\Controller;

use App\Models\Donation;

use Carbon\Carbon;

class DonationController extends Controller {
    public function index (Tournament $tournament) {
        $tournament->load('donations');

        return Inertia::render('Tournaments/Management/Donations/Donations')
                ->with('tournament', $tournament);
    }

    public function create(Tournament $tournament) {
        return Inertia::render('Tournaments/Management/Donations/DonationCreate')
                ->with('tournament', $tournament);
    }

    public function store(Request $request, Tournament $tournament) {
        $request->validate([
            'name'  =>  'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'message' => 'nullable|string',
        ]);

        $donation = $tournament->donations()->create([
            'name' => $request->name,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'message' => $request->message,
        ]);

        return redirect()->route('tournaments.donations.manage', $tournament);
    }

    public function edit(Tournament $tournament, Donation $donation) {
        return Inertia::render('Tournaments/Management/Donations/DonationEdit')
                ->with('tournament', $tournament)
                ->with('donation', $donation);
    }

    public function update(Request $request, Tournament $tournament, Donation $donation) {
        $request->validate([
            'name'  =>  'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'message' => 'nullable|string',
        ]);

        $donation->update([
            'name' => $request->name,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'message' => $request->message,
        ]);

        return redirect()->route('tournaments.donations.manage', $tournament);
    }

    public function destroy(Tournament $tournament, Donation $donation) {
        $donation->delete();

        return redirect()->route('tournaments.donations.manage', $tournament);
    }
}
