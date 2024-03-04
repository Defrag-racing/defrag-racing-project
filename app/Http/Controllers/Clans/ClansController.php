<?php

namespace App\Http\Controllers\Clans;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Clan;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

class ClansController extends Controller {
    public function index () {
        $clans = Clan::with('admin:id,name')
            ->with(['players.user' => function ($query) {
                $query->select('id', 'name', 'profile_photo_path');
            }])
            ->withCount('players')
            ->paginate(20);

        return Inertia::render('Clans/Index', [
            'clans' => $clans
        ]);
    }

    public function create (Request $request) {
        if ($request->user()->clan()->exists()) {
            return redirect()->route('clans.index');
        }

        return Inertia::render('Clans/Create');
    }

    public function store (Request $request) {
        $request->validate([
            'name' => 'required',
            'image' => 'required',
        ]);

        $clan = new Clan();

        $clan->name = $request->name;

        $clan->save();

        return redirect()->route('clans.index');
    }
}
