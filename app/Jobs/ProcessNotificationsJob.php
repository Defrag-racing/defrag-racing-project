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
                $this->sendNotification($record);
            }

        }
    }

    public function sendNotification ($currentRecord) {
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

        $notification->save();
    }

    public function shouldSendNotification ($currentRecord) {
        $previousNotification = RecordNotification::query()
            ->where('user_id', $currentRecord->user_id)
            ->where('mapname', $currentRecord->mapname)
            ->where('physics', $currentRecord->physics)
            ->where('mode', $currentRecord->mode)
            ->where('mdd_id', $this->record->mdd_id)
            ->orderBy('created_at', 'DESC')
            ->first();

        if (! $previousNotification) {
            return true;
        }

        if ($previousNotification->date_set <= $currentRecord->date_set) {
            return true;
        }

        return false;
    }
}
