<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RelatedTournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'related_tournament_id',
    ];
}
