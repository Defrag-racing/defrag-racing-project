<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\RecordNotification;
use App\Models\Notification;

class NotificationsController extends Controller
{
    public function records (Request $request) {
        $notifications = RecordNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(30);

        return Inertia::render('NotificationsView')->with('notifications', $notifications);
    }

    public function recordsclear (Request $request) {
        $notifications = RecordNotification::where('user_id', $request->user()->id)->update([
            'read'  =>  true
        ]);
    }

    public function system (Request $request) {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(30);

        return Inertia::render('SystemNotificationsView')->with('notifications', $notifications);
    }

    public function systemclear (Request $request) {
        $notifications = Notification::where('user_id', $request->user()->id)->update([
            'read'  =>  true
        ]);
    }
}
