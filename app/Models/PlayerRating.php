<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlayerRating extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'mdd_id',
        'user_id',
        'physics',
        'mode',
        'all_players_rank',
        'active_players_rank',
        'category_total_participators',
        'player_records_in_category',
        'last_activity',
        'player_rating',
    ];

    public function user () {
        return $this->belongsTo(User::class, 'mdd_id', 'mdd_id')->select('id', 'name', 'profile_photo_path', 'country', 'mdd_id', 'model', 'avatar_effect', 'name_effect', 'color', 'avatar_border_color');
    }

    /** The q3df profile: the only country a player without an account has. */
    public function mddProfile (): BelongsTo {
        return $this->belongsTo(MddProfile::class, 'mdd_id', 'id')->select('id', 'country');
    }
}
