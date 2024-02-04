<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\User;
use App\Models\Record;

class ProfileController extends Controller {
    public function index(Request $request, User $user) {
        $type = $request->input('type', 'latest');

        $types = ['recentlybeaten', 'tiedranks', 'bestranks', 'besttimes', 'worstranks', 'worsttimes'];

        if (!in_array($type, $types)) {
            $type = 'latest';
        }

        $records = match ($type) {
            'recentlybeaten'    => $this->recentlyBeaten($user),
            'tiedranks'         => $this->tiedRanks($user),
            'bestranks'         => $this->bestRanks($user),
            'besttimes'         => $this->bestTimes($user),
            'worstranks'        => $this->worstRanks($user),
            'worsttimes'        => $this->worstTimes($user),
            default             => $this->latestRecords($user),
        };

        $records = $records->with('map')->paginate(10);

        return Inertia::render('Profile')
            ->with('records', $records)
            ->with('user', $user)
            ->with('type', $type)
            ->with('profile', $user->mdd_profile);
    }

    public function latestRecords(User $user) {
        $records = Record::where('mdd_id', $user->mdd_id)->orderBy('date_set', 'DESC');

        return $records;
    }

    public function recentlyBeaten(User $user) {
        $playerId = $user->mdd_id;

        $records = Record::query();
        return $records;
    }

    public function tiedRanks(User $user) {
        $playerId = $user->mdd_id;

        $playerMaps = Record::where('mdd_id', $playerId)->get(['rank', 'mapname', 'physics']);

        $records = Record::where('mdd_id', '!=', $playerId)
            ->whereIn('mapname', $playerMaps->pluck('mapname'))
            ->where(function ($query) use ($playerMaps) {
                foreach ($playerMaps as $map) {
                    $query->orWhere(function ($subQuery) use ($map) {
                        $subQuery->where('mapname', $map->mapname)
                            ->where('rank', $map->rank)
                            ->where('physics', $map->physics);
                    });
                }
            });

        return $records;
    }

    public function bestRanks(User $user) {
        $records = Record::where('mdd_id', $user->mdd_id)->orderBy('rank', 'ASC')->orderBy('date_set', 'DESC');

        return $records;
    }

    public function bestTimes(User $user) {
        
    }

    public function worstRanks(User $user) {
        $records = Record::where('mdd_id', $user->mdd_id)->orderBy('rank', 'DESC')->orderBy('date_set', 'DESC');

        return $records;
    }

    public function worstTimes(User $user) {
        
    }
}
