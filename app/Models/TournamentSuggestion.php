<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'title',
        'message',
        'done'
    ];
}
