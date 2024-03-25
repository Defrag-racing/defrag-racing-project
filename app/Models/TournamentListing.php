<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image',
        'link',
        'type',
        'subtype',
        'tournament_id',
        'round_id',
        'order',
        'system',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }
}
