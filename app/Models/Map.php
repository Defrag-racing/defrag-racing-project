<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'author',
        'pk3',
        'pk3_size',
        'thumbnail',
        'physics',
        'gametype',
        'mod',
        'weapons',
        'items',
        'functions',
        'visible',
        'date_added'
    ];
}
