<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayersRatings extends Model
{
    use HasFactory;

    // Below must be in syncs with CS_PLAYERS_RATINGS in:
    // storage/ranking_calculator/records_processing.py
    protected $fillable = [
        'name',
        'mdd_id',
        'user_id',
        'physics',
        'mode',
        'player_rating',
    ];
}
