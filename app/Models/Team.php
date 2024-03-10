<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'cpm_player_id', 'vq3_player_id', 'tournament_id'];

    public function cpmPlayer()
    {
        return $this->belongsTo(User::class, 'cpm_player_id');
    }

    public function vq3Player()
    {
        return $this->belongsTo(User::class, 'vq3_player_id');
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function invitations() {
        return $this->hasMany(TeamInvite::class);
    }
}
