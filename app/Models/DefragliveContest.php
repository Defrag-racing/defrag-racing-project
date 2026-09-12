<?php

namespace App\Models;

use App\Services\DefragliveContestFunding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A DefragLive watch-time contest window. Winner is drawn by a watch-time-
 * weighted raffle over the watch sessions in [starts_at, ends_at]
 * (DefragliveWatchService::draw). Default 5 EUR / 2 weeks, but per-row.
 */
class DefragliveContest extends Model
{
    protected $table = 'defraglive_contests';

    /**
     * Currencies a prize may be set in, and how they are written. The prize is
     * paid out of one person's pocket, so this is a short list on purpose - a
     * free-text field invites "eur", "Euro" and "EU" into the same column, and
     * every one of those would then print without a symbol.
     */
    public const CURRENCIES = [
        'EUR' => '€',
        'USD' => '$',
    ];

    public const DEFAULT_CURRENCY = 'EUR';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_PAID = 'paid';
    // Prize resolutions other than a payout: the winner donated it back to the
    // site, or it rolled over into the next contest's prize pool.
    public const STATUS_DONATED = 'donated';
    public const STATUS_FORWARDED = 'forwarded';

    /** Statuses meaning the prize is settled (nothing owed to the winner). */
    public const RESOLVED_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_DONATED,
        self::STATUS_FORWARDED,
    ];

    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'prize_amount',
        'prize_currency',
        'carried_over_amount',
        'status',
        'winner_mdd_id',
        'winner_user_id',
        'winner_name',
        'winner_seconds',
        'winner_tickets',
        'total_tickets',
        'winning_ticket',
        'draw_picks',
        'drawn_at',
        'notes',
    ];

    protected $attributes = [
        'prize_currency' => self::DEFAULT_CURRENCY,
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'drawn_at' => 'datetime',
        'prize_amount' => 'decimal:2',
        'carried_over_amount' => 'decimal:2',
        'winner_seconds' => 'integer',
        'winner_tickets' => 'integer',
        'total_tickets' => 'integer',
        'winning_ticket' => 'integer',
        'draw_picks' => 'array',
    ];

    /**
     * Keep the money that pays for this contest recorded alongside it.
     *
     * Here rather than in the admin screens because a contest is created from
     * more than one place - the resource, the rollover command, a backfill -
     * and a funding row that only appears when somebody used the right screen
     * is worse than none: the donations page would then be short by an amount
     * nobody could see was missing.
     */
    protected static function booted(): void
    {
        $funding = fn (DefragliveContest $contest) => app(DefragliveContestFunding::class)->record($contest);

        static::created($funding);

        // Only when the money or the draft flag moved. Drawing a winner saves
        // the row too, and rewriting the donation on every one of those would
        // touch it for no reason.
        static::updated(function (DefragliveContest $contest) use ($funding) {
            if ($contest->wasChanged(['prize_amount', 'carried_over_amount', 'status', 'starts_at', 'title'])) {
                $funding($contest);
            }
        });

        // The foreign key is nullOnDelete, so without this the donation would
        // survive its contest as a row nothing explains.
        static::deleting(function (DefragliveContest $contest) {
            SiteDonation::where('defraglive_contest_id', $contest->id)->delete();
        });
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    /**
     * A prize written for humans. An unknown currency keeps its code rather
     * than losing the unit, so a bad row reads oddly instead of claiming the
     * prize is in whatever the reader assumes.
     */
    public static function formatPrize($amount, ?string $currency): string
    {
        $value = number_format((float) $amount, 2);
        $symbol = self::CURRENCIES[$currency] ?? null;

        return $symbol === null
            ? trim($value . ' ' . $currency)
            : $symbol . $value;
    }

    public function isOpenForWatching(): bool
    {
        $now = now();

        return $this->starts_at && $this->ends_at
            && $now->betweenIncluded($this->starts_at, $this->ends_at);
    }

    /** The contest currently accepting watch time (drives the public page). */
    public function scopeCurrent($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('starts_at');
    }
}
