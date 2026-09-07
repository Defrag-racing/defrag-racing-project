<?php

namespace App\Filament\Pages;

use App\Models\CompCandidate;
use App\Models\CompRound;
use App\Models\Map;
use App\Models\SiteSetting;
use App\Services\Comps\CandidateSelector;
use App\Services\Comps\CompScheduler;
use App\Services\Comps\CompSettings;
use App\Services\Comps\MapClassifier;
use App\Services\Comps\MapEligibilityTagger;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The one screen comps is run from: the schedule, the rules, the ballot that
 * is open, and the map that is being played.
 *
 * It is deliberately not a form over a table. Almost everything here happens
 * on its own - the point of comps is that nobody has to remember to start it -
 * so what an admin needs is to see what the machinery decided and be able to
 * overrule one map when the draw produces something silly.
 */
class CompsControl extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Comps: control';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.comps-control';

    public bool $enabled = false;

    public string $timezone = 'Europe/Prague';

    public int $startDow = 0;

    public string $startTime = '20:00';

    public int $votingLeadHours = 24;

    public int $poolSize = 5;

    public int $prizeEur = 5;

    /** Candidate being swapped, and what it is being swapped for. */
    public ?int $swapCandidateId = null;

    public string $swapSearch = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $s = app(CompSettings::class);

        $this->enabled = $s->weeklyEnabled();
        $this->timezone = $s->timezone()->getName();
        $this->startDow = $s->startDayOfWeek();
        $this->startTime = $s->startTime();
        $this->votingLeadHours = $s->votingLeadHours();
        $this->poolSize = $s->poolSize();
        $this->prizeEur = $s->prizeEur();
    }

    public function saveSettings(): void
    {
        $this->validate([
            'timezone' => ['required', 'timezone'],
            'startDow' => ['required', 'integer', 'between:0,6'],
            'startTime' => ['required', 'date_format:H:i'],
            'votingLeadHours' => ['required', 'integer', 'between:0,168'],
            'poolSize' => ['required', 'integer', 'between:2,20'],
            'prizeEur' => ['required', 'integer', 'between:0,10000'],
        ]);

        SiteSetting::set(CompSettings::KEY_ENABLED, $this->enabled ? '1' : '0');
        SiteSetting::set(CompSettings::KEY_TIMEZONE, $this->timezone);
        SiteSetting::set(CompSettings::KEY_START_DOW, (string) $this->startDow);
        SiteSetting::set(CompSettings::KEY_START_TIME, $this->startTime);
        SiteSetting::set(CompSettings::KEY_VOTING_LEAD_HOURS, (string) $this->votingLeadHours);
        SiteSetting::set(CompSettings::KEY_POOL_SIZE, (string) $this->poolSize);
        SiteSetting::set(CompSettings::KEY_PRIZE_EUR, (string) $this->prizeEur);

        Notification::make()
            ->title('Saved')
            ->body('Times change the next round to be created, not one already scheduled.')
            ->success()
            ->send();
    }

    /**
     * Redraw the whole ballot. Same pool and the same rules, so it cannot
     * smuggle in a map the draw would have refused - it is for a set that came
     * out badly, not for choosing the maps by hand.
     *
     * Every vote already cast goes with it, because those votes were for maps
     * that are no longer on offer.
     */
    public function rerollBallot(): void
    {
        $round = CompRound::with('candidates')->where('status', 'voting')->orderBy('starts_at')->first();

        if (! $round) {
            Notification::make()->title('No ballot is open')->danger()->send();

            return;
        }

        // The configured pool size, not however many are on the ballot now.
        // Rerolling after removing two maps by hand should give a full ballot
        // back, not permanently shrink it to what was left.
        $wanted = app(CompSettings::class)->poolSize();

        $draw = app(CandidateSelector::class)->draw($round->category, $round->weapon, $wanted);

        if (empty($draw)) {
            Notification::make()->title('Nothing left to draw from')->danger()->send();

            return;
        }

        DB::transaction(function () use ($round, $draw) {
            $round->votes()->delete();
            $round->candidates()->delete();

            foreach ($draw as $map) {
                CompCandidate::create([
                    'comp_round_id' => $round->id,
                    'map_id' => $map['id'],
                    'blocked_physics' => $map['blocked_physics'] ?? null,
                ]);
            }
        });

        Notification::make()
            ->title('Ballot redrawn')
            ->body(count($draw) . ' new map(s). Every vote cast on the old set was removed with it.')
            ->success()
            ->send();
    }

    /** Which TIME_BANDS slot a record time falls in, as its index. */
    private function bandOf(int $wrMs): int
    {
        $seconds = $wrMs / 1000;

        foreach (CandidateSelector::TIME_BANDS as $i => [$from, $to]) {
            if ($seconds >= $from && ($to === null || $seconds < $to)) {
                return $i;
            }
        }

        return 0;
    }

    /** The fastest recorded time on a candidate's map, in milliseconds. */
    private function wrMsOf(CompCandidate $candidate): int
    {
        return (int) DB::table('records')
            ->where('mapname', $candidate->map?->name)
            ->whereNull('deleted_at')
            ->where('time', '>', 0)
            ->min('time');
    }

    /** Take one map off the ballot without putting another in its place. */
    public function removeCandidate(int $candidateId): void
    {
        $candidate = CompCandidate::with('round')->find($candidateId);

        if (! $candidate || ! $candidate->round?->isVoting()) {
            Notification::make()->title('That ballot is closed')->danger()->send();

            return;
        }

        if ($candidate->round->candidates()->count() <= 2) {
            Notification::make()
                ->title('A ballot needs at least two maps')
                ->body('Add another before taking this one off.')
                ->danger()
                ->send();

            return;
        }

        $name = $candidate->map?->name ?? 'map';

        $candidate->votes()->delete();
        $candidate->delete();

        Notification::make()->title("Removed {$name}")->success()->send();
    }

    /**
     * Put a named map on the ballot by hand.
     *
     * Deliberately not held to the category or the never-played rule: this is
     * the manual override, and an admin typing a map name has a reason the
     * draw cannot know about. It does refuse a map already on this ballot,
     * which is a mistake rather than an intention.
     */
    public function addCandidate(): void
    {
        $round = CompRound::where('status', 'voting')->orderBy('starts_at')->first();

        if (! $round) {
            Notification::make()->title('No ballot is open')->danger()->send();

            return;
        }

        $name = trim($this->swapSearch);

        if ($name === '') {
            Notification::make()->title('Type a map name first')->danger()->send();

            return;
        }

        // Case-insensitive: maps.name carries capitals on a few hundred maps
        // where everything downstream is lowercase.
        $map = Map::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? Map::where('name', 'like', $name . '%')->orderBy('name')->first();

        if (! $map) {
            Notification::make()->title("No map called \"{$name}\"")->danger()->send();

            return;
        }

        if ($round->candidates()->where('map_id', $map->id)->exists()) {
            Notification::make()->title("{$map->name} is already on this ballot")->danger()->send();

            return;
        }

        CompCandidate::create([
            'comp_round_id' => $round->id,
            'map_id' => $map->id,
            'blocked_physics' => app(MapEligibilityTagger::class)->blockedPhysicsFor($map),
        ]);

        $this->swapSearch = '';

        Notification::make()->title("Added {$map->name}")->success()->send();
    }

    /**
     * Set what one particular round pays, without touching the default.
     *
     * This is how a donation earmarked for a single weekly is honoured: raise
     * that week only, and the weeks before and after keep their own figure.
     */
    public function setRoundPrize(int $roundId, float $eur): void
    {
        $round = CompRound::find($roundId);

        if (! $round || $round->status === 'finished') {
            Notification::make()->title('That round is over')->danger()->send();

            return;
        }

        // Float, not int: a lump sum spread over a number of weeks that does
        // not divide it lands on halves, and rounding 7.50 down to 7 here
        // would quietly pay out less than was donated.
        $eur = round(max(0, $eur), 2);

        $round->update(['prize_eur' => $eur]);

        Notification::make()
            ->title("Round now pays {$eur} EUR per physics")
            ->body('Only this round. The default under Schedule is unchanged.')
            ->success()
            ->send();
    }

    /** Run the scheduler by hand rather than waiting for the next minute. */
    public function runTick(): void
    {
        $done = app(CompScheduler::class)->tick();

        Notification::make()
            ->title($done ? 'Scheduler ran' : 'Nothing was due')
            ->body($done ? implode("\n", $done) : null)
            ->success()
            ->send();
    }

    /**
     * Replace one map on an open ballot. Redrawn from the same pool, so it
     * still obeys the category, the never-repeat rule and the record filter -
     * an admin swapping a map should not be able to smuggle in something the
     * rules would have refused.
     */
    public function redrawCandidate(int $candidateId): void
    {
        $candidate = CompCandidate::with('round')->find($candidateId);

        if (! $candidate || ! $candidate->round?->isVoting()) {
            Notification::make()->title('That ballot is closed')->danger()->send();

            return;
        }

        $round = $candidate->round;
        $onBallot = $round->candidates()->pluck('map_id')->all();

        $pool = collect(app(CandidateSelector::class)->eligible($round->category, $round->weapon))
            ->reject(fn ($m) => in_array($m['id'], $onBallot, true));

        if ($pool->isEmpty()) {
            Notification::make()->title('Nothing left to draw from')->danger()->send();

            return;
        }

        // Replace like with like. The ballot is drawn one map per band of
        // record time so that a week always has a sprint and always has
        // something long on it; swapping a two minute map for a four second
        // one would undo that on the first press. Falls back to the whole pool
        // only if that band has genuinely run out.
        $sameBand = $pool->filter(fn ($m) => $this->bandOf($m['wr_ms']) === $this->bandOf($this->wrMsOf($candidate)));

        $replacement = $sameBand->isNotEmpty() ? $sameBand->random() : $pool->random();

        $candidate->update([
            'map_id' => $replacement['id'],
            'blocked_physics' => $replacement['blocked_physics'] ?? null,
            'votes_cpm' => 0,
            'votes_vq3' => 0,
        ]);

        // The votes belonged to the map that just left, not to its slot.
        $candidate->votes()->delete();

        Notification::make()
            ->title("Swapped in {$replacement['name']}")
            ->body('Votes cast for the old map were removed with it.')
            ->success()
            ->send();
    }

    public function currentRounds(): array
    {
        return [
            'playing' => CompRound::where('status', 'active')
                ->with(['comp', 'maps.map'])
                ->first(),
            'voting' => CompRound::whereIn('status', ['voting', 'locked'])
                ->with(['comp', 'candidates.map', 'maps.map'])
                ->orderBy('starts_at')
                ->first(),
        ];
    }

    /** What the next few weeks will be, so the rotation is visible ahead. */
    public function upcomingCategories(int $weeks = 6): array
    {
        $selector = app(CandidateSelector::class);
        $latest = (int) \App\Models\Comp::weekly()->max('number');

        $out = [];

        for ($i = 1; $i <= $weeks; $i++) {
            $number = $latest + $i;
            $out[] = [
                'number' => $number,
                'category' => $selector->categoryForWeekly($number),
            ];
        }

        return $out;
    }

    /**
     * Pool sizes, so an empty category is visible before it bites.
     *
     * Cached, because working one out means grouping 800 000 records to find
     * every map's fastest time. Livewire re-renders this page on every button
     * press and each render was paying for all three, which is what made the
     * page take the better part of ten seconds to answer anything.
     *
     * Ten minutes is far shorter than anything that moves these numbers: a
     * pool only shrinks when a map is played, once a week.
     */
    public function poolSizes(): array
    {
        return Cache::remember('comps:pool_sizes', 600, function () {
            $selector = app(CandidateSelector::class);

            $out = [];

            foreach ([MapClassifier::STRAFE, MapClassifier::WEAPON, MapClassifier::COMBO] as $category) {
                $out[$category] = count($selector->eligible($category));
            }

            return $out;
        });
    }

    public function weekdays(): array
    {
        return [
            0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
        ];
    }
}
