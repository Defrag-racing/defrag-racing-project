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
    }
}
