<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecordNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'country',
        'physics',
        'mode',
        'time',
        'mdd_id',
        'record_player_id',
        'mapname',
        'date_set',
        'read',
        'my_time'
    ];
}
