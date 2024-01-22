<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecordHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country',
        'physics',
        'mode',
        'gametype',
        'time',
        'date_set',
        'mdd_id',
        'user_id',
        'mapname',
        'rank',
        'besttime'
    ];

    public function user () {
        return $this->belongsTo(User::class, 'mdd_id', 'mdd_id');
    }

    public function map () {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }
}
