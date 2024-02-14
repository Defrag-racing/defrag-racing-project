<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'image',
        'banner',
        'start_date',
        'end_date',
        'published',
        'approved',
        'has_teams',
        'rules',
        'prize_pool',
        'trailer',
        'has_donations',
        'streamer_window',
        'creator',
    ];
}
