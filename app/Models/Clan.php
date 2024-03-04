<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plain_name',
        'image',
        'admin_id'
    ];

    public function players()
    {
        return $this->hasMany(ClanPlayer::class);
    }

    public function invitations()
    {
        return $this->hasMany(ClanInvitation::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
