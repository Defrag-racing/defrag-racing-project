<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'tournament_id',
        'packed',
        'published',
        'mapname',
        'author',
        'image',
        'weapons',
        'items',
        'functions',
    ];
}
