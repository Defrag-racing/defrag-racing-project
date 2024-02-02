<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\User;
use App\Models\Record;

class ProfileController extends Controller
{
    public function index(User $user) {
        $records = Record::where('mdd_id', $user->mdd_id)->orderBy('date_set', 'DESC')->with('map')->paginate(10);

        return Inertia::render('Profile')
            ->with('records', $records)
            ->with('user', $user);
    }
}
