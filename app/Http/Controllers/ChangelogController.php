<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Announcement;
use App\Models\Changelog;

class ChangelogController extends Controller
{
    public function index (Request $request) {
        $announcements = Changelog::orderBy('created_at', 'DESC')->get();

        return Inertia::render('Changelog')->with('announcements', $announcements);
    }

    public function announcements (Request $request) {
        $announcements = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->get();

        return Inertia::render('Announcements')->with('announcements', $announcements);
    }
}
