<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoundComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'user_id',
        'comment',
    ];
}
