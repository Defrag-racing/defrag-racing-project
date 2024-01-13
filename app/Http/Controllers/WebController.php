<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class WebController extends Controller
{
    function home() {
        // session()->flash('flash.banner', 'Defrag Racing is under maintenance right now !');
        // session()->flash('flash.bannerStyle', 'danger');

        return Inertia::render('Home');
    }

    function servers() {
        return Inertia::render('Servers');
    }
}
