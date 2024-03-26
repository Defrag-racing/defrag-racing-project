<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentNew extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'title',
        'content',
        'image',
        'link',
        'video',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function comments()
    {
        return $this->morphMany(ListingComment::class, 'commentable');
    }
}
