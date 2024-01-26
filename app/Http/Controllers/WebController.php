<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Announcement;

class WebController extends Controller
{
    function home() {
        $announcements = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->get();

        return Inertia::render('Home')->with('announcements', $announcements);
    }
}
