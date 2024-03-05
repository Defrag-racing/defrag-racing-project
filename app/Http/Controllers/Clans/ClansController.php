<?php

namespace App\Http\Controllers\Clans;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Clan;
use App\Models\User;

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
        

        return Inertia::render('Clans/Index')
            ->with('clans', $clans)
            ->with('myClan', $myClan)
            ->with('users', $users);
    }

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
}
