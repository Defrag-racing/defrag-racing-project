<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapRenderOverride extends Model
{
    public const ALLOW = 'allow';
    public const BLOCK = 'block';

    protected $fillable = ['map_name', 'physics', 'mode', 'gamemode', 'note', 'created_by'];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
