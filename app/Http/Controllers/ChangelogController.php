<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Announcement;

class ChangelogController extends Controller
{
    public function index (Request $request) {
        $announcements = Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->get();

        return Inertia::render('Changelog')->with('announcements', $announcements);
    }
}
