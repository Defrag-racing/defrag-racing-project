<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['rconpassword'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'ip',
        'port',
        'location',
        'type',
        'admin_name',
        'admin_contact',
        'ping_url',
        'offline',
        'visible',
        'map',
        'defrag',
        'rconpassword',
        'besttime_country',
        'besttime_name',
        'besttime_url',
        'besttime_time',
        'plain_name'
    ];

    public function mapdata () {
        return $this->belongsTo(Map::class, 'map', 'name');
    }

    public function onlinePlayers () {
        return $this->hasMany(OnlinePlayer::class)->orderByRaw('CASE WHEN time = 0 THEN 1 ELSE 0 END, time ASC');
    }
}
