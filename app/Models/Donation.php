<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tournament_id',
        'name',
        'amount',
        'currency',
        'message',
    ];

    protected $with= ['user'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
