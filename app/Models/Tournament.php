<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'image',
        'banner',
        'start_date',
        'end_date',
        'published',
        'approved',
        'has_teams',
        'rules',
        'prize_pool',
        'trailer',
        'has_donations',
        'streamer_window',
        'creator',
    ];

    public function isOrganizer ($userId) {
        $organizer = Organizer::query()
            ->where('tournament_id', $this->id)
            ->where('user_id', $userId)
            ->pluck('role');

        return $organizer->contains('admin') || $organizer->contains('organizer');
    }

    public function isValidator ($userId) {
        $organizer = Organizer::query()
            ->where('tournament_id', $this->id)
            ->where('user_id', $userId)
            ->where('role', 'validator')
            ->orWhere('role', 'admin')
            ->exists();

        return $organizer;
    }

    public function organizers() {
        return $this->hasMany(Organizer::class);
    }

    public function donations() {
        return $this->hasMany(Donation::class);
    }

    public function faqs() {
        return $this->hasMany(TournamentFaq::class);
    }

    public function suggestions() {
        return $this->hasMany(TournamentSuggestion::class);
    }

    public function streamers () {
        return $this->hasMany(TournamentStreamer::class);
    }

    public function relatedTournaments () {
        return $this->hasMany(RelatedTournament::class);
    }

    public function teams () {
        return $this->hasMany(Team::class);
    }

    public function rounds () {
        return $this->hasMany(Round::class);
    }

    public function news () {
        return $this->hasMany(TournamentNew::class);
    }

    public function clanResults () {
        $rounds = Round::where('tournament_id', $this->id)
                ->where('start_date', '<=', Carbon::now())
                ->orderBy('start_date', 'DESC')
                ->with(['vq3_demos' => function ($query) use($request) {
                    $query->where('user_id', $request->user()->id);
                }])
                ->with(['cpm_demos' => function ($query) use($request) {
                    $query->where('user_id', $request->user()->id);
                }])
                ->with('vq3_results.user.clan')
                ->with('cpm_results.user.clan')
                ->get();

        $clan_results_vq3 = [];
        $clan_results_cpm = [];
    
        foreach($rounds as $round) {
            $vq3_results = $round->vq3_results;
            $cpm_results = $round->cpm_results;

            foreach($vq3_results as $demo) {
                if (! $demo->user->clan) {
                    continue;
                }

                if (! isset($clan_results_vq3[$demo->user->clan->id])) {
                    $clan_results_vq3[$demo->user->clan->id] = [
                        'clan' => $demo->user->clan,
                        'points' => 0,
                        'number' => 0
                    ];
                }

                $clan_results_vq3[$demo->user->clan->id]['points'] += $demo->points;
                $clan_results_vq3[$demo->user->clan->id]['number']++;
            }

            foreach($cpm_results as $demo) {
                if (! $demo->user->clan) {
                    continue;
                }

                if (! isset($clan_results_cpm[$demo->user->clan->id])) {
                    $clan_results_cpm[$demo->user->clan->id] = [
                        'clan' => $demo->user->clan,
                        'points' => 0,
                        'number' => 0
                    ];
                }

                $clan_results_cpm[$demo->user->clan->id]['points'] += $demo->points;
                $clan_results_cpm[$demo->user->clan->id]['number']++;
            }

            $round->clan_results_vq3 = $clan_results_vq3;
            $round->clan_results_cpm = $clan_results_cpm;
        }

        usort($clan_results_vq3, function($a, $b) {
            if ($a['points'] == $b['points']) {
                return $a['rank'] - $b['rank'];
            }

            return $b['points'] - $a['points'];
        });

        usort($clan_results_cpm, function($a, $b) {
            if ($a['points'] == $b['points']) {
                return $a['rank'] - $b['rank'];
            }

            return $b['points'] - $a['points'];
        });

        return [
            'vq3'       =>      $clan_results_vq3,
            'cpm'       =>      $clan_results_cpm
        ];
    }
}
