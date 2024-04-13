<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            ->where('best', true)
            ->groupBy('user_id')
            ->orderBy('time', 'asc');
    }

    public function cpm_results() {
        return $this->hasMany(Demo::class)
            ->where('physics', 'cpm')
            ->where('rejected', false)
            ->where('best', true)
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
        if ($physics == 'vq3') {
            $demos = $this->vq3_results;
        } else {
            $demos = $this->cpm_results;
        }

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

    public function rankPadding($rank) {
        return str_pad($rank, 6, '0', STR_PAD_LEFT);
    }

    public function buildResultsZip($anonymous = false) {
        $zip_name = 'results_' . $this->tournament_id . '_' . $this->id;

        if ($anonymous) {
            $zip_name .= '_anonymous';
        }

        $zip_name .= '.zip';

        if (! $this->published || ! $this->results) {
            return false;
        }

        if (Storage::exists('results/' . $zip_name)) {
            return $zip_name;
        }

        $zip = new \ZipArchive();
        $zip_path = storage_path('app/results/' . $zip_name);

        if ($zip->open($zip_path, \ZipArchive::CREATE) !== TRUE) {
            return false;
        }

        $vq3_demos = $this->vq3_results;
        $cpm_demos = $this->cpm_results;

        $counter = 1;
        $files = [];

        foreach ($vq3_demos as $demo) {
            $filename = $this->rankPadding($demo->rank);

            if (in_array($filename, $files)) {
                $filename .= '_' . $counter;
                $counter++;
            } else {
                $files[] = $filename;
            }

            if ($anonymous) {
                $filename .= '.dm_68';
            } else {
                $filename .= '_' . $demo->filename;
            }

            $zip->addFile(storage_path('app/' . $demo->file), 'vq3/' . $filename);
        }

        $files = [];
        $counter = 1;
        foreach ($cpm_demos as $demo) {
            $filename = $this->rankPadding($demo->rank);

            if (in_array($filename, $files)) {
                $filename .= '_' . $counter;
                $counter++;
            } else {
                $files[] = $filename;
            }

            if ($anonymous) {
                $filename .= '.dm_68';
            } else {
                $filename .= '_' . $demo->filename;
            }

            $zip->addFile(storage_path('app/' . $demo->file), 'cpm/' . $filename);
        }

        $zip->close();

        return $zip_name;
    }
}
