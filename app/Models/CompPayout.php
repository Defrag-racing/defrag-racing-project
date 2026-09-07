<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One winner's prize for one physics of one finished round, and what became
 * of it.
 *
 * A prize is not always money leaving the account. Most weeks somebody takes
 * it, some weeks the winner hands it straight back, and handing it back can
 * mean three different things - the hosting bill, the next weekly's pool,
 * or the DefragLive contest pool. Those are the four endings, and the row exists so that "did we settle
 * week 9" is a question with an answer.
 */
class CompPayout extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_DONATED_SITE = 'donated_site';
    public const STATUS_DONATED_COMPS = 'donated_comps';
    public const STATUS_DONATED_DEFRAGLIVE = 'donated_defraglive';
    /** More than one of the above at once: some taken, some given back. */
    public const STATUS_SPLIT = 'split';

    /** Statuses meaning nothing is owed to the winner any more. */
    public const RESOLVED_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_DONATED_SITE,
        self::STATUS_DONATED_COMPS,
        self::STATUS_DONATED_DEFRAGLIVE,
        self::STATUS_SPLIT,
    ];

    /** How each ending is written, everywhere it is shown. */
    public const LABELS = [
        self::STATUS_PENDING => 'Payout pending',
        self::STATUS_PAID => 'Paid out',
        self::STATUS_DONATED_SITE => 'Donated to the website',
        self::STATUS_DONATED_COMPS => 'Donated to the next comps',
        self::STATUS_DONATED_DEFRAGLIVE => 'Donated to DefragLive',
        self::STATUS_SPLIT => 'Split',
    ];

    /** The amount column each single-ending status fills. */
    public const PART_OF = [
        self::STATUS_PAID => 'paid_eur',
        self::STATUS_DONATED_SITE => 'donated_site_eur',
        self::STATUS_DONATED_COMPS => 'donated_comps_eur',
        self::STATUS_DONATED_DEFRAGLIVE => 'donated_defraglive_eur',
    ];

    protected $fillable = [
        'comp_round_id',
        'physics',
        'user_id',
        'amount',
        'paid_eur',
        'donated_site_eur',
        'donated_comps_eur',
        'donated_defraglive_eur',
        'status',
        'site_donation_id',
        'comps_donation_id',
        'defraglive_donation_id',
        'resolved_at',
        'resolved_by',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_eur' => 'decimal:2',
        'donated_site_eur' => 'decimal:2',
        'donated_comps_eur' => 'decimal:2',
        'donated_defraglive_eur' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(CompRound::class, 'comp_round_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(SiteDonation::class, 'site_donation_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function compsDonation(): BelongsTo
    {
        return $this->belongsTo(SiteDonation::class, 'comps_donation_id');
    }

    public function defragliveDonation(): BelongsTo
    {
        return $this->belongsTo(SiteDonation::class, 'defraglive_donation_id');
    }

    /**
     * Where the money went, as status => euro, only the non-zero parts. A
     * whole-amount settlement is one entry; a split is two or three.
     *
     * @return array<string, float>
     */
    public function parts(): array
    {
        $out = [];

        foreach (self::PART_OF as $status => $column) {
            if ((float) $this->{$column} > 0) {
                $out[$status] = (float) $this->{$column};
            }
        }

        return $out;
    }

    public function isResolved(): bool
    {
        return in_array($this->status, self::RESOLVED_STATUSES, true);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
