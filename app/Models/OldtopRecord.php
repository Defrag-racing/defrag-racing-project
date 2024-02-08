<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldtopRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'plain_name',
        'mapname',
        'time',
        'gametype',
        'physic',
        'rank',
        'country',
        'date_set'
    ];
}
