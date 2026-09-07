<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A time its own owner withdrew. See the migration for why it is a snapshot.
 */
class PlayerSelfReport extends Model
{
    protected $fillable = [
        'user_id',
        'mdd_id',
        'player_name',
        'record_id',
        'mapname',
        'physics',
        'mode',
        'gametype',
        'time',
        'reason',
        'note',
        'handling',
        'processed_at',
        'processed_by',
        'resolution',
        'detached',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'detached' => 'array',
    ];

    /**
     * When the player wants it handled. `mode` was already taken by the game
     * mode of the run, so the column is `handling` - naming it `mode` twice
     * would have been a bug waiting for whoever reads this next.
     *
     * A method rather than a const so lang:sync can see the literals - see
     * RecordFlagController::flagTypes() for why a const put them out of reach.
     * The keys go in the database; only the labels are translated.
     */
    public static function handling(): array
    {
        return [
            'immediate' => __('Hide it here as soon as an admin approves'),
            'on_merge' => __('Wait for the mDd merge and do both at once'),
        ];
    }

    public const RESOLUTIONS = [
        'hidden' => 'Hidden by an admin',
        'beaten' => 'Beaten by a new run',
        'restored' => 'Put back by an admin',
    ];

    public function scopePending($query)
    {
        return $query->whereNull('processed_at');
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function wasBeaten(): bool
    {
        return $this->resolution === 'beaten';
    }

    /** What the player sees as the state of their request. */
    public function wasRestored(): bool
    {
        return $this->resolution === 'restored';
    }

    /**
     * Did this request take the run off the board, and is it still off?
     *
     * "Beaten" never hid anything - the player replaced the time themselves -
     * and "restored" already put it back, so neither is undoable.
     */
    public function hidRun(): bool
    {
        return $this->isProcessed() && ! $this->wasBeaten() && ! $this->wasRestored();
    }

    public function stateLabel(): string
    {
        if ($this->wasBeaten()) {
            return __('Beaten - resolved');
        }

        if ($this->wasRestored()) {
            return __('Put back on the board');
        }

        if ($this->isProcessed()) {
            return __('Off the board');
        }

        return $this->handling === 'immediate'
            ? __('Waiting for an admin')
            : __('Queued for the mDd merge');
    }

    /**
     * Hide the run, remembering what that pulled off it.
     *
     * Record::deleting detaches uploaded demos and nothing puts them back, so
     * without this snapshot a restore would silently cost the record its demo,
     * the YouTube render hanging off that demo, and its time history entry.
     */
    public function hideRun(?int $adminId): bool
    {
        $run = Record::find($this->record_id);

        if (! $run) {
            return false;
        }

        $demos = UploadedDemo::where('record_id', $run->id)
            ->whereIn('status', ['assigned', 'fallback-assigned'])
            ->get(['id', 'status'])
            ->map(fn ($demo) => ['id' => $demo->id, 'status' => $demo->status])
            ->all();

        $run->delete();

        $this->update([
            'processed_at' => now(),
            'processed_by' => $adminId,
            'resolution' => 'hidden',
            'detached' => $demos ?: null,
        ]);

        return true;
    }

    /**
     * Put the run back, demos and all.
     *
     * A demo is only re-attached if it is still loose: demos:rematch-all may
     * have given it to somebody else in the meantime, and taking it back off
     * them would trade one wrong answer for another.
     */
    public function restoreRun(?int $adminId = null): bool
    {
        // Only what THIS request took down. A record can be gone because some
        // other admin removed it for some other reason, and the amnesty is not
        // allowed to undo that just because the run happens to be missing.
        if (! $this->hidRun()) {
            return false;
        }

        $run = Record::onlyTrashed()->whereKey($this->record_id)->first();

        if (! $run) {
            return false;
        }

        $run->restore();

        foreach ($this->detached ?? [] as $demo) {
            UploadedDemo::whereKey($demo['id'] ?? null)
                ->whereNull('record_id')
                ->update([
                    'record_id' => $run->id,
                    'status' => $demo['status'] ?? 'assigned',
                ]);
        }

        // The row stays. Deleting it would leave the panel unable to answer
        // "what happened to that request" - and a run that was taken down and
        // put back is exactly the case somebody asks about later.
        $this->update([
            'processed_at' => $this->processed_at ?? now(),
            'processed_by' => $adminId ?? $this->processed_by,
            'resolution' => 'restored',
            'detached' => null,
        ]);

        return true;
    }

    /**
     * Demos the amnesty is currently holding down.
     *
     * Hiding a run detaches its demos, and `demos:rematch-all` picks up loose
     * demos every Monday, fuzzy-matches the nickname and files them as offline
     * records - which would put the withdrawn run back in front of everybody a
     * week after the player was told it was gone. These are off limits to it
     * until the request is restored, which clears the list by itself.
     */
    public static function withheldDemoIds(): array
    {
        return static::query()
            ->whereNotNull('processed_at')
            ->whereNotNull('detached')
            ->where(fn ($q) => $q->whereNull('resolution')->orWhereNotIn('resolution', ['beaten', 'restored']))
            ->pluck('detached')
            ->flatMap(fn ($demos) => collect($demos)->pluck('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The serverdemo of this run, if it was set on one of our servers.
     *
     * Matched from the snapshot rather than through the record: hiding the run
     * soft-deletes it, and the withdrawal has to stay reviewable afterwards.
     * Same key the validation queue uses - map, player, time, physics, mode.
     */
    public function serverDemo(): ?ServerDemo
    {
        return $this->memo('serverDemo', fn () => ServerDemo::query()
            ->where('map_name', $this->mapname)
            ->where('mdd_id', $this->mdd_id)
            ->where('time_ms', $this->time)
            ->when($this->physics, fn ($q, $p) => $q->where('physics', strtolower($p)))
            ->when($this->mode, fn ($q, $m) => $q->where('mode', strtolower($m)))
            ->first());
    }

    /**
     * The demo the player uploaded here, if any.
     *
     * Tried by record first, then by the run itself, because deleting a record
     * detaches its uploaded demos (Record::deleting) - so after the run is
     * hidden the link is gone and only the map, time and player are left.
     */
    public function uploadedDemo(): ?UploadedDemo
    {
        return $this->memo('uploadedDemo', function () {
            if ($this->record_id) {
                $byRecord = UploadedDemo::where('record_id', $this->record_id)->first();

                if ($byRecord) {
                    return $byRecord;
                }
            }

            return UploadedDemo::query()
                ->where('map_name', $this->mapname)
                ->where('time_ms', $this->time)
                ->where(function ($q) {
                    $q->where('user_id', $this->user_id)
                        ->orWhere('player_name', $this->player_name);
                })
                ->first();
        });
    }

    /** A published YouTube render of this run, if one exists. */
    public function video(): ?RenderedVideo
    {
        return $this->memo('video', fn () => RenderedVideo::query()
            ->whereNotNull('youtube_url')
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->where('record_id', $this->record_id)
                    ->orWhere(fn ($inner) => $inner
                        ->where('map_name', $this->mapname)
                        ->where('time_ms', $this->time)
                        ->where('player_name', $this->player_name));
            })
            ->first());
    }

    /**
     * Each lookup runs once per row. The admin table asks the same question in
     * the badge column and again in every download action.
     *
     * On the instance, NOT in a static: under Octane a static outlives the
     * request and the whole worker keeps answering from it. It did - the panel
     * went on offering a serverdemo that had been deleted three requests
     * earlier. This dies with the row that owns it.
     */
    private array $lookups = [];

    private function memo(string $key, \Closure $resolve)
    {
        if (! array_key_exists($key, $this->lookups)) {
            $this->lookups[$key] = $resolve();
        }

        return $this->lookups[$key];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function map()
    {
        return $this->belongsTo(Map::class, 'mapname', 'name');
    }
}
