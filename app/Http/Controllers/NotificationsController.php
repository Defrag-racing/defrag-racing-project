<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\RecordNotification;

class NotificationsController extends Controller
{
    public function index (Request $request) {
        $notifications = RecordNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(30);

        return Inertia::render('NotificationsView')->with('notifications', $notifications);
    }

    public function clear (Request $request) {
        $notifications = RecordNotification::where('user_id', $request->user()->id)->update([
            'read'  =>  true
        ]);
    }
}
