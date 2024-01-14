<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnlinePlayer extends Model
{
    use HasFactory;

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
}
