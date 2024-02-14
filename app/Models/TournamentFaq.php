<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentFaq extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'question',
        'answer',
        'order'
    ];
}
