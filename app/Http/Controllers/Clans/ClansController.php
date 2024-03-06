<?php

namespace App\Http\Controllers\Clans;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Clan;
use App\Models\User;
use App\Models\ClanInvitation;
use App\Models\ClanPlayer;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

class ClansController extends Controller {
    public function index (Request $request) {
        $clans = Clan::with('admin:id,name')
            ->with(['players.user' => function ($query) {
                $query->select('id', 'name', 'profile_photo_path');
            }])
            ->withCount('players')
            ->paginate(20);

        if ($request->user()) {
            $myClan = $request->user()
                ->clan()
                ->with('admin:id,name')
                ->with(['players.user' => function ($query) {
                    $query->select('id', 'name', 'profile_photo_path', 'country', 'plain_name');
                }])
                ->withCount('players')
                ->first();

            $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);
        } else {
            $myClan = null;
            $users = [];
        }

        $invitations = ClanInvitation::query()
            ->where('user_id', $request->user()->id)
            ->where('accepted', false)
            ->with('clan')
            ->get();
        

        return Inertia::render('Clans/Index')
            ->with('clans', $clans)
            ->with('myClan', $myClan)
            ->with('users', $users)
            ->with('invitations', $invitations);
    }

    public function show(Clan $clan, Request $request) {
        $clan->load('players');
    }

    public function accept(ClanInvitation $invitation, Request $request) {
        if ($invitation->user_id !== $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are not allowed to do that');
        }

        $invitation->accepted = true;
        $invitation->save();

        $clanPlayer = new ClanPlayer();
        $clanPlayer->clan_id = $invitation->clan_id;
        $clanPlayer->user_id = $request->user()->id;

        $clanPlayer->save();

        return redirect()->route('clans.index')->withSuccess('You have joined the clan');
    }

    public function reject(ClanInvitation $invitation, Request $request) {
        if ($invitation->user_id !== $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are not allowed to do that');
        }

        $invitation->delete();

        return redirect()->route('clans.index')->withSuccess('You have rejected the invitation');
    }
}
