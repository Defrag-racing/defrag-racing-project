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
}
