<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Map extends Model
{
    use HasFactory;
    use Searchable;

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

    public function toSearchableArray () {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    public function records () {
        return $this->hasMany(Record::class, 'mapname', 'name')->orderBy('date_set', 'DESC');
    }
}
