<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'image',
        'banner',
        'start_date',
        'end_date',
        'published',
        'approved',
        'has_teams',
        'rules',
        'prize_pool',
        'trailer',
        'has_donations',
        'streamer_window',
        'creator',
    ];

    public function isOrganizer ($userId) {
        $organizer = Organizer::query()
            ->where('tournament_id', $this->id)
            ->where('user_id', $userId)
            ->pluck('role');

        return $organizer->contains('admin') || $organizer->contains('organizer');
    }

    public function isValidator ($userId) {
        $organizer = Organizer::query()
            ->where('tournament_id', $this->id)
            ->where('user_id', $userId)
            ->where('role', 'validator')
            ->orWhere('role', 'admin')
            ->exists();

        return $organizer;
    }

    public function organizers() {
        return $this->hasMany(Organizer::class);
    }

    public function donations() {
        return $this->hasMany(Donation::class);
    }

    public function faqs() {
        return $this->hasMany(TournamentFaq::class);
    }

    public function suggestions() {
        return $this->hasMany(TournamentSuggestion::class);
    }

    public function streamers () {
        return $this->hasMany(TournamentStreamer::class);
    }

    public function relatedTournaments () {
        return $this->hasMany(RelatedTournament::class);
    }

    public function teams () {
        return $this->hasMany(Team::class);
    }

    public function rounds () {
        return $this->hasMany(Round::class);
    }

    public function news () {
        return $this->hasMany(TournamentNew::class);
    }
}
