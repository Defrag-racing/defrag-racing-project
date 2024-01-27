<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecordHistory extends Model
{
    use HasFactory;
    use SoftDeletes;

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
        return $this->belongsTo(User::class, 'mdd_id', 'mdd_id')->select('id', 'name', 'profile_photo_path', 'country', 'mdd_id', 'model');
    }

    public function map () {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }
}
