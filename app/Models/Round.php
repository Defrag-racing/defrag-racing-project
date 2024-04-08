<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mapname',
        'category',
        'start_date',
        'end_date',
        'author',
        'weapons',
        'items',
        'functions',
        'image',
        'tournament_id',
        'results',
        'published'
    ];

    public function tournament() {
        return $this->belongsTo(Tournament::class);
    }

    public function maps() {
        return $this->hasMany(RoundMap::class);
    }

    public function comments() {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function vq3_demos() {
        return $this->hasMany(Demo::class)->where('physics', 'vq3')->orderBy('time', 'asc');
    }

    public function cpm_demos() {
        return $this->hasMany(Demo::class)->where('physics', 'cpm')->orderBy('time', 'asc');
    }

    public function vq3_results() {
        return $this->hasMany(Demo::class)
            ->where('physics', 'vq3')
            ->where('rejected', false)
            ->groupBy('user_id')
            ->orderBy('time', 'asc');
    }

    public function cpm_results() {
        return $this->hasMany(Demo::class)
            ->where('physics', 'cpm')
            ->where('rejected', false)
            ->groupBy('user_id')
            ->orderBy('time', 'asc');
    }

    public function calculateResults() {
        $this->calculate_physics_results('vq3');
        $this->calculate_physics_results('cpm');

        $this->results = true;
        $this->save();
    }

    private function calculate_physics_results($physics) {
        $demos = Demo::where('round_id', $this->id)
            ->where('physics', $physics)
            ->where('rejected', false)
            ->groupBy('user_id')
            ->orderBy('time', 'asc')
            ->get();

        if ($demos->count() == 0) {
            return;
        }

        $best_time = $demos[0]->time;
        $rank = 1;
        $last_time = -1;

        foreach ($demos as $demo) {
            if ($demo->time == $last_time) {
                $rank--;
            }

            $demo->rank = $rank;

            $last_time = $demo->time;

            $c1 = $best_time / $demo->time;

            if ($rank <= 50) {
                $percentage_points = 1 - (($rank - 1) * 0.01);
            } else if (rank <= 100) {
                $percentage_points = 0.5 - (($rank - 51) * 0.005);
            } else {
                $percentage_points = 0.25 - (($rank - 101) * 0.0025);
            }

            $c2 = $percentage_points;

            $demo->points = round(($c1 * $c2) * 1000);

            $demo->save();

            $rank++;

            if (! $demo->counted) {
                $rank--;
            }
        }
    }
}
