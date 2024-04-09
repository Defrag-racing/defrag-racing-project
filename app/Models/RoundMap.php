<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoundMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'crc',
        'name',
        'download_name',
        'pk3',
        'external'
    ];

    public function round() {
        return $this->belongsTo(Round::class);
    }
}
