<?php

namespace App\Services\Comps;

use App\Models\CompPayout;
use App\Models\CompResult;
use App\Models\CompRound;
use App\Models\SiteDonation;
use Illuminate\Support\Facades\DB;

/**
 * Turns a finished round into money owed, and records how that money was
 * settled.
 *
 * Nothing here pays anybody. There is no payment provider behind comps and
 * there is not going to be one for five euro a week - the transfer happens by
 * hand. What was missing was the *list*: which weeks are still owed, to whom,
 * and how much. That is all this is.
 */
class PrizePayouts
{
    public function __construct(private PrizeFunding $funding)
    {
    }

    /**
     * Create the pending rows for a round that has just been frozen.
     *
     * Idempotent, so re-freezing a round after a report invalidated an entry
     * can call it again. A winner who has been overtaken by that re-freeze
     * keeps their row: it is already settled or it is already a promise
     * somebody read, and quietly deleting either would be worse than an admin
     * seeing two rows and reading the round.
     *
     * @return int  rows created
     */
    public function ensureFor(CompRound $round): int
    {
        if ($round->status !== 'finished') {
            return 0;
        }

        $prize = round($this->funding->forRound($round), 2);

        if ($prize <= 0) {
            return 0;
        }

        $created = 0;

        foreach (BallotResolver::PHYSICS as $physics) {
            $winners = CompResult::where('comp_round_id', $round->id)
                ->where('physics', $physics)
                ->winners()
                ->get();

            if ($winners->isEmpty()) {
                continue;
            }

            // A tie splits the prize the same way it splits the points. Two
            // people first on the same map is one first place, not two
            // budgets, and the alternative is a week that quietly costs twice
            // what it advertised.
            $share = round($prize / $winners->count(), 2);

            foreach ($winners as $winner) {
                $row = CompPayout::firstOrCreate(
                    [
                        'comp_round_id' => $round->id,
                        'physics' => $physics,
                        'user_id' => $winner->user_id,
                    ],
                    [
                        'amount' => $share,
                        'status' => CompPayout::STATUS_PENDING,
                    ]
                );

                if ($row->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Every finished round that has no rows yet, for the rounds that were
     * played before this existed.
     *
     * @return int  rows created
     */
    public function backfill(): int
    {
        $created = 0;

        CompRound::where('status', 'finished')
            ->with('comp')
            ->orderBy('starts_at')
            ->each(function (CompRound $round) use (&$created) {
                $created += $this->ensureFor($round);
            });

        return $created;
    }

    /**
     * Settle one prize with the whole amount going one way.
     *
     * @param  array{comps_start_comp?:int, comps_weeks?:int, note?:string}  $options
     */
    public function resolve(CompPayout $payout, string $status, array $options = []): CompPayout
    {
        if (! isset(CompPayout::PART_OF[$status])) {
            throw new \InvalidArgumentException("Not a settled status: {$status}");
        }

        $amount = (float) $payout->amount;

        return $this->settle($payout, [$status => $amount], $options);
    }

    /**
     * Settle one prize, in parts.
     *
     * The parts must add up to the prize: a settlement that leaves a euro
     * unaccounted for is not settled, and one that hands out more than was
     * won is a typo. Both given-back parts write a real SiteDonation each,
     * which is the whole point of choosing between them here rather than in
     * a note: a prize given back has to appear in the donations the same way
     * any other money does, or the person who gave it up does not get counted
     * as having given anything. The winner's own email goes on it, because
     * donor stats are aggregated by email match and without it the row
     * belongs to nobody.
     *
     * @param  array<string, float>  $parts  status => euro, from PART_OF
     * @param  array{comps_start_comp?:int, comps_weeks?:int, note?:string}  $options
     */
    public function settle(CompPayout $payout, array $parts, array $options = []): CompPayout
    {
        $paid = round((float) ($parts[CompPayout::STATUS_PAID] ?? 0), 2);
        $site = round((float) ($parts[CompPayout::STATUS_DONATED_SITE] ?? 0), 2);
        $comps = round((float) ($parts[CompPayout::STATUS_DONATED_COMPS] ?? 0), 2);
        $live = round((float) ($parts[CompPayout::STATUS_DONATED_DEFRAGLIVE] ?? 0), 2);

        if (min($paid, $site, $comps, $live) < 0) {
            throw new \InvalidArgumentException('A part of a prize cannot be negative.');
        }

        $sum = $paid + $site + $comps + $live;

        if (abs($sum - (float) $payout->amount) > 0.005) {
            throw new \InvalidArgumentException(sprintf(
                'The parts add up to %.2f EUR, the prize is %.2f EUR.',
                $sum,
                (float) $payout->amount
            ));
        }

        $ways = count(array_filter([$paid, $site, $comps, $live], fn ($eur) => $eur > 0));

        if ($ways === 0) {
            throw new \InvalidArgumentException('Nothing was settled.');
        }

        $status = match (true) {
            $ways > 1 => CompPayout::STATUS_SPLIT,
            $paid > 0 => CompPayout::STATUS_PAID,
            $site > 0 => CompPayout::STATUS_DONATED_SITE,
            $comps > 0 => CompPayout::STATUS_DONATED_COMPS,
            default => CompPayout::STATUS_DONATED_DEFRAGLIVE,
        };

        return DB::transaction(function () use ($payout, $paid, $site, $comps, $live, $status, $options) {
            $siteDonation = $site > 0 ? $this->recordDonation($payout, $site, 'site', $options) : null;
            $compsDonation = $comps > 0 ? $this->recordDonation($payout, $comps, 'comps', $options) : null;
            $liveDonation = $live > 0 ? $this->recordDonation($payout, $live, 'defraglive', $options) : null;

            $payout->update([
                'status' => $status,
                'paid_eur' => $paid,
                'donated_site_eur' => $site,
                'donated_comps_eur' => $comps,
                'donated_defraglive_eur' => $live,
                'site_donation_id' => $siteDonation?->id,
                'comps_donation_id' => $compsDonation?->id,
                'defraglive_donation_id' => $liveDonation?->id,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'note' => trim((string) ($options['note'] ?? '')) ?: null,
            ]);

            return $payout->refresh();
        });
    }

    /**
     * The donation a given-back part of a prize becomes.
     *
     * @param  'site'|'comps'|'defraglive'  $to  which pot the money lands in
     */
    private function recordDonation(CompPayout $payout, float $amount, string $to, array $options): SiteDonation
    {
        $toComps = $to === 'comps';
        $toLive = $to === 'defraglive';

        $payout->loadMissing(['user', 'round.comp']);

        $number = $payout->round?->comp?->number;
        $url = $payout->round?->comp_id ? url('/comps/' . $payout->round->comp_id) : url('/comps');

        $what = 'Comps weekly' . ($number ? " #{$number}" : '')
            . ' ' . strtoupper($payout->physics) . ' prize donated back - ' . $url;

        return SiteDonation::create([
            'user_id' => $payout->user_id,
            'donor_email' => $payout->user?->email,
            'donor_name' => $this->plainName($payout) ?: 'Comps winner',
            'amount' => $amount,
            'currency' => 'EUR',
            'donation_date' => now()->toDateString(),
            'note' => $what,
            'status' => 'approved',
            // All three or none: PrizeFunding only counts a row where every
            // one of them is filled in, so a half-filled row would sit in
            // neither pot and pay for nothing.
            //
            // Zero rather than null on the amount, because the column is NOT
            // NULL with a default of 0 - handing it a null does not mean "no
            // earmark", it throws and the prize is left unsettled.
            'comps_amount' => $toComps ? $amount : 0,
            'comps_weeks' => $toComps ? max(1, (int) ($options['comps_weeks'] ?? 1)) : null,
            'comps_start_comp' => $toComps
                ? (int) ($options['comps_start_comp'] ?? $this->funding->nextFundableComp())
                : null,
            'comps_note' => $toComps ? 'Donated winnings from ' . $what : null,
            // The DefragLive pool is the same earmark a contest's own prize
            // money carries, without a contest: it pays whichever comes next.
            'defraglive_amount' => $toLive ? $amount : 0,
            'defraglive_note' => $toLive ? 'Donated winnings from ' . $what : null,
        ]);
    }

    /** The winner's nick with its Quake colour codes taken out. */
    private function plainName(CompPayout $payout): string
    {
        return trim((string) preg_replace('/\^[0-9A-Za-z]/', '', (string) $payout->user?->name));
    }
}
