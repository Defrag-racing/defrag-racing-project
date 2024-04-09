<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\User;
use App\Models\Record;
use App\Models\MddProfile;

use Illuminate\Support\Facades\DB;

class ProfileController extends Controller {
    public function index(Request $request, $userId) {
        $user = User::query()
            ->where('id', $userId)
            ->with('clan')
            ->first(['id', 'mdd_id', 'name', 'profile_photo_path', 'country', 'color']);

        if (! $user) {
            return redirect()->route('profile.mdd', $userId);
        }

        if (! $user->mdd_id ) {
            return Inertia::render('Profile')
                ->with('user', $user)
                ->with('hasProfile', false);
        }

        $worldRecordsCpm = Record::where('mdd_id', $user->mdd_id)->where('rank', 1)->where('physics', 'cpm')->count();
        $worldRecordsVq3 = Record::where('mdd_id', $user->mdd_id)->where('rank', 1)->where('physics', 'vq3')->count();

        $type = $request->input('type', 'latest');

        $types = ['recentlybeaten', 'tiedranks', 'bestranks', 'besttimes', 'worstranks', 'worsttimes'];

        if (!in_array($type, $types)) {
            $type = 'latest';
        }

        $records = match ($type) {
            'recentlybeaten'    => $this->recentlyBeaten($user->mdd_id),
            'tiedranks'         => $this->tiedRanks($user->mdd_id),
            'bestranks'         => $this->bestRanks($user->mdd_id),
            'besttimes'         => $this->bestTimes($user->mdd_id),
            'worstranks'        => $this->worstRanks($user->mdd_id),
            'worsttimes'        => $this->worstTimes($user->mdd_id),
            default             => $this->latestRecords($user->mdd_id),
        };

        $records = $records->with('map')->paginate(10)->withQueryString();

        return Inertia::render('Profile')
            ->with('records', $records)
            ->with('user', $user)
            ->with('type', $type)
            ->with('cpm_world_records', $worldRecordsCpm)
            ->with('vq3_world_records', $worldRecordsVq3)
            ->with('type', $type)
            ->with('profile', $user->mdd_profile)
            ->with('hasProfile', true);
    }

    public function mdd(Request $request, $userId) {
        $user = MddProfile::where('id', $userId)->with('user')->first();

        if (! $user) {
            return redirect()->route('home');
        }

        $worldRecordsCpm = Record::where('mdd_id', $user->id)->where('rank', 1)->where('physics', 'cpm')->count();
        $worldRecordsVq3 = Record::where('mdd_id', $user->id)->where('rank', 1)->where('physics', 'vq3')->count();

        $type = $request->input('type', 'latest');

        $types = ['recentlybeaten', 'tiedranks', 'bestranks', 'besttimes', 'worstranks', 'worsttimes'];

        if (!in_array($type, $types)) {
            $type = 'latest';
        }

        $records = match ($type) {
            'recentlybeaten'    => $this->recentlyBeaten($user->id),
            'tiedranks'         => $this->tiedRanks($user->id),
            'bestranks'         => $this->bestRanks($user->id),
            'besttimes'         => $this->bestTimes($user->id),
            'worstranks'        => $this->worstRanks($user->id),
            'worsttimes'        => $this->worstTimes($user->id),
            default             => $this->latestRecords($user->id),
        };

        $records = $records->with('map')->paginate(10)->withQueryString();

        return Inertia::render('Profile')
            ->with('records', $records)
            ->with('user', $user->user)
            ->with('type', $type)
            ->with('cpm_world_records', $worldRecordsCpm)
            ->with('vq3_world_records', $worldRecordsVq3)
            ->with('type', $type)
            ->with('profile', $user)
            ->with('hasProfile', true);
    }

    public function latestRecords($mddId) {
        $records = Record::where('mdd_id', $mddId)->orderBy('date_set', 'DESC');

        return $records;
    }

    public function recentlyBeaten($mddId) {
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

    public function tiedRanks($mddId) {
        $records = Record::select('r1.*')
            ->from('records as r1')
            ->join('records as r2', function ($join) use($mddId) {
                $join->on('r1.mapname', '=', 'r2.mapname')
                    ->on('r1.gametype', '=', 'r2.gametype')
                    ->on('r1.rank', '=', 'r2.rank')
                    ->where('r1.mdd_id', '!=', $mddId);
            })
            ->where('r1.deleted_at', '=', null)
            ->where('r2.mdd_id', '=', $mddId)
            ->where('r2.deleted_at', '=', null)
            ->withTrashed()
            ->orderBy('r1.date_set', 'DESC');

        return $records;
    }

    public function bestRanks($mddId) {
        $records = Record::where('mdd_id', $mddId)->orderBy('rank', 'ASC')->orderBy('date_set', 'DESC');

        return $records;
    }

    public function bestTimes($mddId) {
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

    public function worstRanks($mddId) {
        $records = Record::where('mdd_id', $mddId)->orderBy('rank', 'DESC')->orderBy('date_set', 'DESC');

        return $records;
    }

    public function worstTimes($mddId) {
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
