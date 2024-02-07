<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Announcement;

class WebController extends Controller
{
    public function home() {
        $announcements = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->get();

        return Inertia::render('Home')->with('announcements', $announcements);
    }

    public function flags($flag) {
        return response()->file(public_path() . '/images/flags/_404.png');
    }

    public function thumbs($image) {
        return response()->file(public_path() . '/images/unknown.jpg');
    }
}
