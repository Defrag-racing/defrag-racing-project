<?php

namespace App\Http\Controllers\Clans;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Clan;
use App\Models\User;
use App\Models\ClanInvitation;
use App\Models\ClanPlayer;

use App\Http\Controllers\Controller;

use Intervention\Image\Facades\Image;

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

        $image = $request->file('image');
        $img = Image::make($image);

        $width = $img->width();
        $height = $img->height();

        $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
        $uploadPath = public_path('storage/clans');

        if ($width > 128 || $height > 128) {
            $image = Image::make($image)->fit(128, 128);
            $image->save($uploadPath . '/' . $imageName);
            $file = 'clans/' . $imageName;
        } else {
            $file = $image->store('clans', 'public');
        }

        $clan = new Clan();

        $clan->name = $request->name;
        $clan->image = $file;
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

        $player->systemNotify('clan_invite', 'The Clan ', $myClan->name, ' has invited you to join their clan.', route('clans.show', $myClan));

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

        $player->systemNotify('clan_kick', 'You have been kicked out of the ', $myClan->name, ' Clan.', route('clans.show', $myClan));

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

        $myClan->admin->systemNotify('clan_leave', 'The player ', $request->user()->name, ' Has left your clan.', route('profile.index', $request->user()));

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

        $player->systemNotify('clan_transfer', 'You are now the admin of clan ', $myClan->name, ' .', route('clans.show', $myClan));

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

        ClanInvitation::where('clan_id', $myClan->id)->delete();
        
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
            $image = $request->file('image');
            $img = Image::make($image);

            $width = $img->width();
            $height = $img->height();

            $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
            $uploadPath = public_path('storage/clans');

            if ($width > 128 || $height > 128) {
                $image = Image::make($image)->fit(128, 128);
                $image->save($uploadPath . '/' . $imageName);
                $file = 'clans/' . $imageName;
            } else {
                $file = $image->store('clans', 'public');
            }

            $clan->image = $file;
        }

        $clan->save();

        return redirect()->route('clans.index')->withSuccess('Clan updated Successfully');
    }
}
