<?php

namespace App\Models;

use App\Http\Controllers\RecordsController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Record extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'physics',
        'mode',
        'gametype',
        'time',
        'date_set',
        'mdd_id',
        'user_id',
        'mapname',
        'rank',
        'besttime'
    ];

    public function user () {
        return $this->belongsTo(User::class, 'mdd_id', 'mdd_id')->select('id', 'name', 'profile_photo_path', 'country', 'mdd_id', 'model', 'avatar_effect', 'name_effect', 'color', 'avatar_border_color');
    }

    public function map () {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }

    public function uploadedDemos () {
        return $this->hasMany(UploadedDemo::class, 'record_id', 'id');
    }

    public function approvedFlags()
    {
        return $this->hasMany(RecordFlag::class)->where('status', 'approved');
    }

    public function renderedVideos()
    {
        return $this->hasMany(RenderedVideo::class, 'record_id');
    }

    /**
     * Boot method to clear profile cache when records change
     */
    protected static function boot() {
        parent::boot();

        // Clear profile cache when record is created
        // Note: prependToCache is called AFTER processRanks() in the scraper jobs,
        // so the rank is available when the record enters the page cache.
        static::created(function ($record) {
            if ($record->mdd_id) {
                self::clearProfileCache($record->mdd_id);
            }
        });

        // On update, just clear profile cache (record edits don't change page order)
        static::updated(function ($record) {
            if ($record->mdd_id) {
                self::clearProfileCache($record->mdd_id);
            }
        });

        // Before delete (soft or hard), detach any UploadedDemos pointing at
        // this Record. Otherwise demos would dangle on a soft-deleted record
        // (frontend hides soft-deleted records, so the demo would vanish
        // from the UI). After detach, the next demos:rematch-all run will
        // attribute the demo to its real player via Pass 2 (fuzzy nick) and
        // create an offline_record so the historical attempt surfaces under
        // the player's current live record as a Time History sibling.
        static::deleting(function ($record) {
            $detached = \App\Models\UploadedDemo::where('record_id', $record->id)
                ->whereIn('status', ['assigned', 'fallback-assigned'])
                ->update([
                    'record_id' => null,
                    'status'    => 'processed',
                ]);
            if ($detached > 0) {
                \Illuminate\Support\Facades\Log::info('Auto-detached uploaded_demos from record being deleted', [
                    'record_id' => $record->id,
                    'mapname' => $record->mapname,
                    'gametype' => $record->gametype,
                    'time' => $record->time,
                    'detached_count' => $detached,
                ]);
            }
        });

        // On delete, decrement counts and invalidate affected pages
        static::deleted(function ($record) {
            if ($record->mdd_id) {
                self::clearProfileCache($record->mdd_id);
            }
            RecordsController::removeFromCache($record);
        });

        // A restored record counts on the profile again.
        static::restored(function ($record) {
            if ($record->mdd_id) {
                self::clearProfileCache($record->mdd_id);
            }
        });
    }

    /**
     * Clear all profile cache for a player
     */
    protected static function clearProfileCache($mddId) {
        Cache::forget("profile:stats:{$mddId}");
        Cache::forget("profile:competitors:{$mddId}:cpm");
        Cache::forget("profile:competitors:{$mddId}:vq3");
        Cache::forget("profile:rivals:{$mddId}:cpm");
        Cache::forget("profile:rivals:{$mddId}:vq3");

        \Log::info("Profile cache cleared for player {$mddId}");
    }
}
