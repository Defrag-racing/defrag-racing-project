<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'vq3_player_id',
        'cpm_player_id',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function vq3Player()
    {
        return $this->belongsTo(User::class, 'vq3_player_id');
    }

    public function cpmPlayer()
    {
        return $this->belongsTo(User::class, 'cpm_player_id');
    }
}
