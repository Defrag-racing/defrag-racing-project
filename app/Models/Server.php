<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

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
        'besttime_time'
    ];

    public function mapdata () {
        return $this->belongsTo(Map::class, 'map', 'name');
    }

    public function onlinePlayers () {
        return $this->hasMany(OnlinePlayer::class);
    }
}
