<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentStreamer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'twitch_username',
    ];

    public function tournament() {
        return $this->belongsTo(Tournament::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
