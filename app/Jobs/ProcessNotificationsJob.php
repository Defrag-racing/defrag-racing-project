<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Record;
use App\Models\User;
use App\Models\RecordNotification;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ProcessNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Record $record) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        $records = Record::where('user_id', '!=', NULL)
            ->where('user_id', '!=', $this->record->user_id)
            ->where('mapname', $this->record->mapname)
            ->where('physics', $this->record->physics)
            ->where('mode', $this->record->mode)
            ->where('gametype', $this->record->gametype)
            ->where('time', '>', $this->record->time)
            ->with('user')
            ->get();

        foreach($records as $record) {
            $user = User::where('mdd_id', $record->mdd_id)->first();

            if (!$user) {
                continue;
            }

            $settings = $user->notification_settings;

            if ($settings == 'all' || $settings == $this->record->physics) {
                $this->sendNotification($record, $user);
            }

        }
    }

    public function sendNotification ($currentRecord, User $user = null) {
        // WR-beaten = the *recipient* held rank 1 and the new record took it.
        // processRanks() ran in the record import before this job dispatched, so
        // these ranks reflect the post-beat state: beater is now 1, recipient
        // dropped to 2.
        $worldrecord = ($this->record->rank == 1) && ($currentRecord->rank == 2);

        // "WR Only" for this physics: to them, a time being taken back on some
        // map is noise, and losing the world record is not. The setting has
        // been on the settings page all along with nothing reading it.
        $wanted = $this->record->physics === 'cpm'
            ? ($user?->records_cpm ?? 'all')
            : ($user?->records_vq3 ?? 'all');

        if ($wanted === 'wr' && ! $worldrecord) {
            return;
        }

        if (! $this->shouldSendNotification($currentRecord)) {
            return;
        }

        $notification = new RecordNotification();

        $notification->user_id = $currentRecord->user_id;
        $notification->name = $this->record->name;
        $notification->country = $this->record->country;
        $notification->physics = $this->record->physics;
        $notification->mode = $this->record->mode;
        $notification->time = $this->record->time;
        $notification->mdd_id = $this->record->mdd_id;
        $notification->record_player_id = $this->record->user_id;
        $notification->mapname = $this->record->mapname;
        $notification->date_set = $this->record->date_set;
        $notification->my_time = $currentRecord->time;
        $notification->worldrecord = $worldrecord;

        $notification->save();
    }

    public function shouldSendNotification ($currentRecord) {
        // Create a cache key for this specific notification check
        $cacheKey = sprintf(
            'notification_check:%d:%s:%s:%s:%d',
            $currentRecord->user_id,
            $currentRecord->mapname,
            $currentRecord->physics,
            $currentRecord->mode,
            $this->record->mdd_id
        );

        // Cache the result for 5 minutes to avoid repeated DB queries
        $previousNotification = Cache::remember($cacheKey, 300, function () use ($currentRecord) {
            return RecordNotification::query()
                ->select(['id', 'date_set', 'created_at']) // Only select needed columns
                ->where('user_id', $currentRecord->user_id)
                ->where('mapname', $currentRecord->mapname)
                ->where('physics', $currentRecord->physics)
                ->where('mode', $currentRecord->mode)
                ->where('mdd_id', $this->record->mdd_id)
                ->orderBy('created_at', 'DESC')
                ->first();
        });

        if (! $previousNotification) {
            return true;
        }

        if ($previousNotification->date_set <= $currentRecord->date_set) {
            // Invalidate cache when sending a new notification
            Cache::forget($cacheKey);
            return true;
        }

        return false;
    }
}
