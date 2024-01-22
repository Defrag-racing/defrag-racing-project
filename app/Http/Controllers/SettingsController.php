<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function socialmedia(Request $request) {
        $user = $request->user();

        if ($request->has('twitter_name')) {
            $user->twitter_name = $request->twitter_name;
        }

        if ($request->has('twitch_name')) {
            $user->twitch_name = $request->twitch_name;
        }

        if ($request->has('discord_name')) {
            $user->discord_name = $request->discord_name;
        }

        $user->save();
    }
}
