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


            $invitations = ClanInvitation::query()
                ->where('user_id', $request->user()->id)
                ->where('accepted', false)
                ->with('clan')
                ->get();

            $users = User::query()->orderBy('plain_name')->get(['id', 'name', 'country', 'plain_name']);
        } else {
            $myClan = null;
            $users = [];
            $invitations = [];
        }
        

        return Inertia::render('Clans/Index')
            ->with('clans', $clans)
            ->with('myClan', $myClan)
            ->with('users', $users)
            ->with('invitations', $invitations);
    }

    public function show(Clan $clan, Request $request) {
        $players = User::whereHas('clan', function ($query) use ($clan) {
            $query->where('clan_id', $clan->id);
        })->get(['id', 'name', 'profile_photo_path', 'country', 'plain_name']);

        return Inertia::render('Clans/Show')
            ->with('clan', $clan)
            ->with('players', $players);
    }

    public function accept(ClanInvitation $invitation, Request $request) {
        if ($invitation->user_id !== $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are not allowed to do that');
        }

        if ($request->user()->clan()->first()->admin_id === $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are the admin of another clan, you need to either transfer ownership or dismantle your current clan before joining another one.');
        }

        $invitation->accepted = true;
        $invitation->save();

        $clanPlayers = ClanPlayer::query()
            ->where('user_id', $request->user()->id)
            ->delete();

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
