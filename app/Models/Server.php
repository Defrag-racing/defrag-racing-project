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

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'geo_checked_at' => 'datetime',
        'cheats' => 'boolean',
    ];

    protected $appends = ['besttime_profile_url'];

    /**
     * Where the holder of the best time lives on the site: their account's
     * profile when they linked one, their q3df profile otherwise, nothing
     * when the map has no time yet. Built here once so every card links the
     * same way instead of each guessing what kind of id it holds.
     */
    public function getBesttimeProfileUrlAttribute(): ?string
    {
        if ($this->besttime_url) {
            return route('profile.index', $this->besttime_url);
        }

        if ($this->besttime_mdd_id) {
            return route('profile.mdd', $this->besttime_mdd_id);
        }

        return null;
    }

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
        'latitude',
        'longitude',
        'type',
        'admin_name',
        'admin_contact',
        'ping_url',
        'online',
        'visible',
        'map',
        'defrag',
        'rconpassword',
        'besttime_country',
        'besttime_name',
        'besttime_url',
        'besttime_mdd_id',
        'besttime_time',
        'plain_name',
        'sftp_credential_id',
    ];

    public function mapdata () {
        return $this->belongsTo(Map::class, 'map', 'name');
    }

    public function onlinePlayers () {
        return $this->hasMany(OnlinePlayer::class)->orderByRaw('CASE WHEN time = 0 THEN 1 ELSE 0 END, time ASC');
    }

    public function sftpCredential()
    {
        return $this->belongsTo(SftpCredential::class);
    }
}
