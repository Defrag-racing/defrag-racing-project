<?php

namespace App\Http\Controllers\Clans;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Clan;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

class ManageClanController extends Controller {
    public function index (Request $request) {
        $myClan = $request->user()
            ->clan()
            ->with('admin:id,name')
            ->with(['players.user' => function ($query) {
                $query->select('id', 'name', 'profile_photo_path', 'country');
            }])
            ->withCount('players')
            ->first();

        return Inertia::render('Clans/Manage')
            ->with('clan', $myClan);
    }
}
