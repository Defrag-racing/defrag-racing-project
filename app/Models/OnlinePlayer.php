<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnlinePlayer extends Model
{
    use HasFactory;

    protected $with = ['profile.clan'];

    protected $fillable = [
        'name',
        'time',
        'client_id',
        'mdd_id',
        'nospec',
        'country',
        'follow_num',
        'server_id',
        'model',
        'headmodel'
    ];

    public function spectators() {
        return $this->hasMany(OnlinePlayer::class, 'follow_num', 'client_id');
    }

    public function profile() {
        return $this->belongsTo(User::class, 'mdd_id', 'mdd_id')->select('id', 'name', 'profile_photo_path', 'country', 'mdd_id', 'model');
    }
}
