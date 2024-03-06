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

class ManageClanController extends Controller {
    public function create (Request $request) {
        if ($request->user()->clan()->exists()) {
            return redirect()->route('clans.index');
        }

        if (Clan::where('admin_id', $request->user()->id)->exists()) {
            return redirect()->route('clans.index');
        }

        return Inertia::render('Clans/Create');
    }

    public function store (Request $request) {
        if ($request->user()->clan()->exists()) {
            return redirect()->route('clans.index');
        }

        if (Clan::where('admin_id', $request->user()->id)->exists()) {
            return redirect()->route('clans.index');
        }

        $request->validate([
            'name' => 'required',
            'image' => 'required',
        ]);

        $clan = new Clan();

        $clan->name = $request->name;
        $clan->image = $request->file('image')->store('clans', 'public');
        $clan->admin_id = $request->user()->id;

        $clan->save();

        $clanPlayer = $clan->players()->create([
            'user_id' => $request->user()->id
        ]);

        return redirect()->route('clans.index')->withSuccess('Clan created Successfully');
    }

    public function invite (Request $request) {
        $myClan = $request->user()->clan()->first();
        if (! $myClan) {
            return redirect()->back()->withDanger('You are not in a clan.');
        }

        if ($myClan->admin_id !== $request->user()->id) {
            return redirect()->back()->withDanger('You are not the admin of the clan.');
        }

        $request->validate([
            'player_id' => 'required|exists:users,id',
        ]);

        $player = User::find($request->player_id);

        if (! $player) {
            return redirect()->back()->withDanger('The player does not exist.');
        }

        $clanPlayer = ClanPlayer::where('clan_id', $myClan->id)->where('user_id', $request->player_id)->first();

        if ($clanPlayer) {
            return redirect()->back()->withDanger('The player is already in the clan.');
        }

        $invite = ClanInvitation::create();

        $invite->clan_id = $myClan->id;
        $invite->user_id = $player->id;

        $invite->save();

        return redirect()->back()->withSuccess('The player has been invited to the clan.');
    }

    public function kick (Request $request) {
        $myClan = $request->user()->clan()->first();

        if (! $myClan) {
            return redirect()->back()->withDanger('You are not in a clan.');
        }

        if ($myClan->admin_id !== $request->user()->id) {
            return redirect()->back()->withDanger('You are not the admin of the clan.');
        }

        $request->validate([
            'player_id' => 'required|exists:users,id',
        ]);


        $player = User::find($request->player_id);

        if (! $player) {
            return redirect()->back()->withDanger('The player does not exist.');
        }

        if ($myClan->admin_id === $player->id) {
            return redirect()->back()->withDanger('You cannot kick the admin of the clan.');
        }

        $clanPlayer = ClanPlayer::where('clan_id', $myClan->id)->where('user_id', $request->player_id)->first();

        if (! $clanPlayer) {
            return redirect()->back()->withDanger('The player is not in the clan.');
        }

        $clanPlayer->delete();

        return redirect()->back()->withSuccess('The player has been kicked from the clan.');
    }

    public function leave (Request $request) {
        $myClan = $request->user()->clan()->first();

        if (! $myClan) {
            return redirect()->back()->withDanger('You are not in a clan.');
        }

        if ($myClan->admin_id === $request->user()->id) {
            return redirect()->back()->withDanger('You are the admin of the clan, you cannot leave the clan.');
        }

        $clanPlayer = ClanPlayer::where('clan_id', $myClan->id)->where('user_id', $request->user()->id)->first();

        if (! $clanPlayer) {
            return redirect()->back()->withDanger('You are not in the clan.');
        }

        $clanPlayer->delete();

        return redirect()->back()->withSuccess('You have left the clan.');
    }

    public function transfer (Request $request) {
        $myClan = $request->user()->clan()->first();

        if (! $myClan) {
            return redirect()->back()->withDanger('You are not in a clan.');
        }

        if ($myClan->admin_id !== $request->user()->id) {
            return redirect()->back()->withDanger('You are not the admin of the clan.');
        }

        $request->validate([
            'player_id' => 'required|exists:users,id',
        ]);


        $player = User::find($request->player_id);

        if (! $player) {
            return redirect()->back()->withDanger('The player does not exist.');
        }

        if ($myClan->admin_id === $player->id) {
            return redirect()->back()->withDanger('You already have ownership of this clan.');
        }

        $clanPlayer = ClanPlayer::where('clan_id', $myClan->id)->where('user_id', $request->player_id)->first();

        if (! $clanPlayer) {
            return redirect()->back()->withDanger('The player is not in the clan.');
        }

        $myClan->admin_id = $player->id;
        $myClan->save();

        return redirect()->back()->withSuccess('Ownership of the clan has been transferred.');
    }

    public function dismantle(Request $request) {
        $myClan = $request->user()->clan()->first();

        if (! $myClan) {
            return redirect()->back()->withDanger('You are not in a clan.');
        }

        if ($myClan->admin_id !== $request->user()->id) {
            return redirect()->back()->withDanger('You are not the admin of the clan.');
        }

        $clanPlayers = ClanPlayer::where('clan_id', $myClan->id)->count();

        if ($clanPlayers > 1) {
            return redirect()->back()->withDanger('You cannot dismantle the clan while there are still members in it.');
        }

        ClanPlayer::where('clan_id', $myClan->id)->delete();
        
        $myClan->delete();

        return redirect()->back()->withSuccess('The clan has been dismantled.');
    }

    public function update (Clan $clan, Request $request) {
        if ($clan->admin_id !== $request->user()->id) {
            return redirect()->route('clans.index')->withDanger('You are not the admin of the clan.');
        }

        $request->validate([
            'name' => 'required',
        ]);

        $clan->name = $request->name;

        if ($request->file('image')) {
            $clan->image = $request->file('image')->store('clans', 'public');
        }

        $clan->save();

        return redirect()->route('clans.index')->withSuccess('Clan updated Successfully');
    }
}
