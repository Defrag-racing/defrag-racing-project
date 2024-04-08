<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamInvite extends Model
{
    use HasFactory;

    protected $fillable = ['team_id', 'user_id', 'accepted'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accept()
    {
        $this->update(['accepted' => true]);
        $this->team->update(['cpm_player_id' => $this->user_id]);
    }

    public function reject()
    {
        $this->delete();
    }
}
