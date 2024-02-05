<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\User;
use App\Models\Record;

use Illuminate\Support\Facades\DB;

class ProfileController extends Controller {
    public function index(Request $request, User $user) {
        $worldRecordsCpm = Record::where('mdd_id', $user->mdd_id)->where('rank', 1)->where('physics', 'cpm')->count();
        $worldRecordsVq3 = Record::where('mdd_id', $user->mdd_id)->where('rank', 1)->where('physics', 'vq3')->count();

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

        $records = $records->with('map')->paginate(10)->withQueryString();

        return Inertia::render('Profile')
            ->with('records', $records)
            ->with('user', $user)
            ->with('type', $type)
            ->with('cpm_world_records', $worldRecordsCpm)
            ->with('vq3_world_records', $worldRecordsVq3)
            ->with('type', $type)
            ->with('profile', $user->mdd_profile);
    }

    public function latestRecords(User $user) {
        $records = Record::where('mdd_id', $user->mdd_id)->orderBy('date_set', 'DESC');

        return $records;
    }

    public function recentlyBeaten(User $user) {
        $mddId = $user->mdd_id;

        $records =  Record::selectRaw("
            a.*,
                (SELECT COUNT(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype AND time < a.time ORDER BY time) AS rank_num,
                (SELECT COUNT(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype) AS rank_total,
                b.time AS my_time
        ")
        ->from('records as a')
        ->leftJoin('records as b', 'a.mapname', '=', 'b.mapname')
        ->whereRaw('a.time < b.time')
        ->whereRaw('NOT a.mdd_id = b.mdd_id')
        ->whereRaw('a.gametype = b.gametype')
        ->whereRaw('b.mdd_id = ?', [$mddId])
        ->whereRaw('a.deleted_at IS NULL')
        ->withTrashed()
        ->orderByRaw('a.date_set DESC');
        

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
        $mddId = $user->mdd_id;

        $records =  Record::selectRaw("
            a.*,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype AND time<a.time ORDER by time) as rank_num,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype) as rank_total,
                (SELECT time FROM records WHERE mapname=a.mapname AND gametype=a.gametype ORDER BY TIME LIMIT 1) AS time_first,
                (SELECT 1-((rank_num+1)/(rank_total+1))) AS skill
        ")
        ->from('records as a')
        ->whereRaw('a.mdd_id = ?', [$mddId])
        ->whereRaw('a.deleted_at IS NULL')
        ->withTrashed()
        ->orderBy('skill', 'DESC')
        ->orderByRaw('a.date_set DESC');

        return $records;
    }

    public function worstRanks(User $user) {
        $records = Record::where('mdd_id', $user->mdd_id)->orderBy('rank', 'DESC')->orderBy('date_set', 'DESC');

        return $records;
    }

    public function worstTimes(User $user) {
        $mddId = $user->mdd_id;

        $records =  Record::selectRaw("
            a.*,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype AND time<a.time ORDER by time) as rank_num,
                (SELECT count(id) FROM records WHERE mapname=a.mapname AND gametype=a.gametype) as rank_total,
                (SELECT time FROM records WHERE mapname=a.mapname AND gametype=a.gametype ORDER BY TIME LIMIT 1) AS time_first,
                (SELECT 1-((rank_num+1)/(rank_total+1))) AS skill
        ")
        ->from('records as a')
        ->whereRaw('a.mdd_id = ?', [$mddId])
        ->whereRaw('a.deleted_at IS NULL')
        ->withTrashed()
        ->orderBy('skill', 'ASC')
        ->orderByRaw('a.date_set DESC');

        return $records;
    }
}
