<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Map;
use App\Models\User;

class SearchController extends Controller
{
    public function search(Request $request) {
        $request->validate([
            'search'    =>      ['required', 'string', 'min:1', 'max:255']
        ]);

        $maps = Map::search($request->search)->paginate(25);

        $players = User::search($request->search)->paginate(10);

        return [
            'maps'      =>  $maps,
            'players'   => $players
        ];
    }
}
