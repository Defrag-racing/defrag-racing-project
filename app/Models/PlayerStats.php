<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerStats extends Model
{
    use HasFactory;

    protected $fillable = [
        'mdd_id',
        'total_records',
        'cpm_records',
        'vq3_records',
        'cpm_ctf_records',
        'vq3_ctf_records',
        'average_rank',
        'world_records',
        'strafe_records',
        'rocket_records',
        'grenade_records',
        'plasma_records',
        'bfg_records',
        'strafe_records',
    ];
}
