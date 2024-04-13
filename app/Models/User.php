<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;

use Carbon\Carbon;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'username',
        'country',
        'admin',
        'oldhash',
        'mdd_id',
        'twitter_name',
        'twitch_name',
        'discord_name',
        'model',
        'plain_name',
        'notification_settings',
        'created_at',
        'color',
        'defrag_news',
        'tournament_news',
        'records_vq3',
        'records_cpm',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'oldhash',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [];

    public function username () {
        return 'username';
    }

    public function generateSubstrings($name) {
        $inputString = mb_convert_encoding($name, 'Windows-1252', "auto");
        $length = strlen($inputString);
        $result = [];
    
        for ($i = 0; $i <= $length; $i++) {
            $sub = substr($inputString, $i);

            if (strlen($sub) < 3) {
                break;
            }

            $result[] = $sub;
        }

        if (count($result) == 0) {
            $result[] = $inputString;
        }
    
        return $result;
    }

    public function toSearchableArray () {
        return [
            'id' => (string) $this->id,
            'plain_name' => $this->generateSubstrings($this->plain_name),
            'created_at' => Carbon::parse($this->created_at)->timestamp,
        ];
    }

    public function canAccessPanel(Panel $panel): bool {
        return $this->admin;
    }

    public function getFilamentAvatarUrl(): ?string {
        return $this->profile_photo_path;
    }

    public function getFilamentName(): string {
        $pattern = '/\^\w/';

        $plainName = preg_replace($pattern, '', $this->name);

        return $plainName;
    }

    public function mdd_profile() {
        return $this->hasOne(MddProfile::class, 'id', 'mdd_id');
    }

    public function clan () {
        return $this->hasOneThrough(Clan::class, ClanPlayer::class, 'user_id', 'id', 'id', 'clan_id');
    }

    public function team() {
        return $this->hasOne(Team::class, 'cpm_player_id', 'id') ?? $this->hasOne(Team::class, 'vq3_player_id', 'id');
    }

    public function teamInvites() {
        return $this->hasMany(TeamInvite::class, 'user_id', 'id');
    }

    public function tournamentNotify($type, $before, $headline, $after, $url) {
        if (! $this->tournament_news) {
            return;
        }

        if (Notification::where('user_id', $this->id)->where('type', $type)->where('headline', $headline)->exists()) {
            return;
        }

        $notification = new Notification();
        $notification->user_id = $this->id;
        $notification->type = $type;
        $notification->before = $before;
        $notification->headline = $headline;
        $notification->after = $after;
        $notification->url = $url;
        $notification->save();
    }

    public function systemNotify($type, $before, $headline, $after, $url) {
        $notification = new Notification();
        $notification->user_id = $this->id;
        $notification->type = $type;
        $notification->before = $before;
        $notification->headline = $headline;
        $notification->after = $after;
        $notification->url = $url;
        $notification->save();
    }

    public function systemNotifyAnnouncement($type, $before, $headline, $after, $url) {
        if (! $this->defrag_news) {
            return;
        }

        $notification = new Notification();
        $notification->user_id = $this->id;
        $notification->type = $type;
        $notification->before = $before;
        $notification->headline = $headline;
        $notification->after = $after;
        $notification->url = $url;
        $notification->save();
    }

    public function recordsNotify() {
        
    }

    public function demos() {
        return $this->hasMany(Demo::class);
    }

    public function check_demos($round_id) {
        $demos = $this->demos()
            ->where('round_id', $round_id)
            ->where('rejected', false)
            ->where('physics', 'vq3')
            ->orderBy('time', 'asc');

        $demos->update(['best' => false]);

        $best_demo = $demos->first();

        if ($best_demo) {
            $best_demo->best = true;
            $best_demo->save();
        }

        $demos = $this->demos()
            ->where('round_id', $round_id)
            ->where('rejected', false)
            ->where('physics', 'cpm')
            ->orderBy('time', 'asc');

        $demos->update(['best' => false]);

        $best_demo = $demos->first();

        if ($best_demo) {
            $best_demo->best = true;
            $best_demo->save();
        }
    }
}
