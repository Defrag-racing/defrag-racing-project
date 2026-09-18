<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource;
use App\Models\CompDemoReport;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\OfflineRecord;
use App\Models\UploadedDemo;
use App\Services\Comps\UploadGuard;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * One round, seen player by player.
 *
 * The submissions table answers "what is in the standings". This answers the
 * question that actually comes up when somebody writes in: *what happened to
 * my demo*. So it starts from the person rather than from the entry, and it
 * shows the demos that produced no entry at all next to the ones that did -
 * a file the parser could not read, a run older than the round, a run in the
 * other physics. Those are invisible everywhere else precisely because they
 * are not entries, and they are what people ask about.
 *
 * Almost nothing here changes anything - it is a place to look before
 * answering. The one exception is putting a withdrawn run back, which belongs
 * on this page because this is the only screen a withdrawn run appears on at
 * all: withdrawing deletes the entry, so the submissions table has no row to
 * act on.
 */
class CompsRoundReview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Comps: round review';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.comps-round-review';

    public ?int $roundId = null;

    /** Hide the runs that are simply fine, which is most of them. */
    public bool $onlyProblems = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->roundId = CompRound::where('status', 'active')->latest('starts_at')->value('id')
            ?? CompRound::latest('starts_at')->value('id');
    }

    /** The rounds to choose from, newest first. */
    public function roundOptions(): array
    {
        return CompRound::with('comp')
            ->latest('starts_at')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (CompRound $r) => [$r->id => sprintf(
                '#%s %s · %s · %s',
                $r->comp?->number ?? '?',
                $r->category ?? '',
                $r->status,
                $r->starts_at?->format('d.m.Y') ?? '-'
            )])
            ->all();
    }

    public function round(): ?CompRound
    {
        return $this->roundId
            ? CompRound::with(['comp', 'maps.map', 'candidates.map'])->find($this->roundId)
            : null;
    }

    /**
     * Everything the round touched, grouped by the person it belongs to.
     *
     * Two sources, deliberately merged: the entries, and the demos of the
     * round's maps that never became entries. Reading only the first would
     * show a clean round every time somebody's demo silently failed to enter,
     * which is the exact case this page exists for.
     */
    public function players(): array
    {
        $round = $this->round();

        if (! $round) {
            return [];
        }

        $entries = CompSubmission::where('comp_round_id', $round->id)
            ->with(['user:id,name', 'demo' => fn ($q) => $q->withUnreleasedComps()])
            ->get();

        $enteredDemoIds = $entries->pluck('uploaded_demo_id')->filter()->all();

        $loose = $this->demosOfTheRoundsMaps($round)
            ->reject(fn (UploadedDemo $demo) => in_array($demo->id, $enteredDemoIds, true));

        $demoIds = array_merge($enteredDemoIds, $loose->pluck('id')->all());

        // Paired with a record: online through the demo's own record_id, and
        // offline through the record pointing back at the demo. Neither is a
        // verdict about the run - it is a nicety of the site - but a demo
        // nobody could pair is the one worth a second look.
        $offlinePaired = OfflineRecord::whereIn('demo_id', $demoIds)->pluck('demo_id')->all();

        $reports = CompDemoReport::whereIn('uploaded_demo_id', $demoIds)
            ->where('status', 'open')
            ->get()
            ->groupBy('uploaded_demo_id');

        $guard = app(UploadGuard::class);
        $rows = collect();

        foreach ($entries as $entry) {
            $demo = $entry->demo;

            $rows->push([
                'user' => $entry->user?->name ?? ('#' . $entry->user_id),
                'demo_id' => $entry->uploaded_demo_id,
                'filename' => $demo?->original_filename ?? '(the demo is gone)',
                'physics' => $entry->physics,
                'time' => $entry->time ?: null,
                'verdict' => match ($entry->status) {
                    'valid' => 'Counts',
                    'pending' => 'Being read',
                    default => 'Does not count',
                },
                'tone' => match ($entry->status) {
                    'valid' => 'success',
                    'pending' => 'gray',
                    default => 'danger',
                },
                'reason' => $entry->invalid_reason,
                // What the parser literally wrote down, for the hover. The
                // sentence above explains it; this is the raw pair, which is
                // what somebody comparing two demos actually wants to read.
                'notes' => $this->rawNotes($demo),
                'restorable' => false,
                'entered' => true,
                'auto' => (bool) $entry->auto_entered,
                'online' => (bool) $entry->is_online,
                'paired' => $entry->matched_record_id !== null
                    || $demo?->record_id !== null
                    || in_array($entry->uploaded_demo_id, $offlinePaired, true),
                'reports' => $reports->get($entry->uploaded_demo_id)?->count() ?? 0,
                'at' => $demo?->created_at,
            ]);
        }

        foreach ($loose as $demo) {
            $kind = $this->looseKind($guard, $round, $demo);

            $rows->push([
                'user' => $demo->user?->name ?? ('#' . $demo->user_id),
                'demo_id' => $demo->id,
                // Only while the round is still taking uploads. Afterwards the
                // standings are frozen and putting a run back would rewrite a
                // result everybody has already seen.
                'restorable' => $kind === 'withdrawn' && $round->acceptsUploads(),
                'filename' => $demo->original_filename,
                'physics' => $demo->physics,
                'time' => $demo->time_ms ?: null,
                'verdict' => match ($kind) {
                    'unreadable' => 'Could not be read',
                    'too_old' => 'Older than the round',
                    'other_physics' => 'The other physics',
                    'ballot' => 'Waiting on the vote',
                    default => 'Not entered',
                },
                'tone' => $kind === 'unreadable' ? 'danger' : 'warning',
                // The other physics is not a refusal and not a hold - the demo
                // is public like any other - so it does not get the sentence
                // the player-facing notices use, which promises a later
                // appearance. It is here to be seen, not explained.
                'reason' => match ($kind) {
                    'not_entered' => null,
                    'other_physics' => 'A run on this map in the other physics. Public straight away.',
                    default => $guard->noticeText($kind),
                },
                'notes' => $this->rawNotes($demo),
                'entered' => false,
                'auto' => false,
                'online' => ! $demo->is_offline,
                'paired' => $demo->record_id !== null || in_array($demo->id, $offlinePaired, true),
                'reports' => $reports->get($demo->id)?->count() ?? 0,
                'at' => $demo->created_at,
            ]);
        }

        return $rows
            ->when($this->onlyProblems, fn (Collection $c) => $c->filter(
                fn (array $r) => $r['tone'] !== 'success' || ! $r['paired'] || $r['reports'] > 0
            ))
            ->groupBy('user')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Collection $rows, string $user) => [
                'user' => $user,
                'rows' => $rows->sortBy('at')->values()->all(),
                'problems' => $rows->filter(fn (array $r) => $r['tone'] !== 'success')->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Every demo of the round's maps that arrived while the round was a thing,
     * entered or not.
     *
     * By upload time rather than by the hold, because a finished round has
     * released its holds and this page is most useful after the fact. The
     * filename is matched as well as the parsed map name: a demo the parser
     * could not read has no map name at all, and those are the ones somebody
     * is most likely to be asking about.
     */
    private function demosOfTheRoundsMaps(CompRound $round): Collection
    {
        // A round still being voted on has no winning map yet, only its
        // candidates. The guard already holds demos on every candidate, so
        // those are the maps to look at until the ballot closes.
        $source = $round->maps->isNotEmpty() ? $round->maps : $round->candidates;

        $maps = $source
            ->map(fn ($m) => mb_strtolower(trim((string) $m->map?->name)))
            ->filter()
            ->unique()
            ->values();

        if ($maps->isEmpty() || ! $round->voting_opens_at) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, $maps->count(), '?'));

        return UploadedDemo::withUnreleasedComps()
            ->where('created_at', '>=', $round->voting_opens_at)
            ->where('created_at', '<=', $round->ends_at ?? now())
            ->where(function ($q) use ($maps, $placeholders) {
                $q->whereRaw("LOWER(map_name) IN ($placeholders)", $maps->all());

                foreach ($maps as $name) {
                    $q->orWhere('original_filename', 'like', addcslashes($name, '%_\\') . '[%');
                }
            })
            ->with('user:id,name')
            ->limit(500)
            ->get();
    }

    /**
     * Why a demo did not enter.
     *
     * The guard answers this against the rounds as they stand right now, which
     * is the truth for the round being played and nonsense for one that ended
     * three weeks ago. So an old round only says "not entered", except for a
     * file the parser could not read - that one is a fact about the file and
     * stays true forever.
     */
    private function looseKind(UploadGuard $guard, CompRound $round, UploadedDemo $demo): string
    {
        if ($demo->status === 'failed') {
            return 'unreadable';
        }

        // Voting, locked and active are all rounds of right now, so the
        // guard's answer is still true for them. Only a finished round gets
        // the plain "not entered".
        return $round->status === 'finished' ? 'not_entered' : $guard->noticeKind($demo);
    }

    /**
     * Put a run somebody withdrew back into the round, on their request.
     *
     * Withdrawing is one-way by design: the guard refuses to re-enter a demo
     * it sees a withdrawal stamp on, so that a re-parse cannot quietly undo
     * somebody's decision. This is the deliberate exception, and it is an
     * admin action rather than a button for the player, because a run that
     * can be pulled and pushed back at will is a way to play with the
     * standings rather than with the map.
     *
     * The stamp comes off and the guard is asked to do the entering, so the
     * run goes through the same map, physics and window checks as any other.
     * If the guard still says no, the stamp goes back on and nothing has
     * changed - the alternative is a demo left in neither state.
     */
    public function restore(int $demoId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $round = $this->round();

        abort_unless($round && $round->acceptsUploads(), 403);

        $demo = UploadedDemo::withUnreleasedComps()->find($demoId);

        if (! $demo || ! $demo->comps_withdrawn_at) {
            Notification::make()->danger()->title('That demo is not withdrawn.')->send();

            return;
        }

        $stamp = $demo->comps_withdrawn_at;

        $demo->comps_withdrawn_at = null;
        $demo->saveQuietly();

        app(UploadGuard::class)->apply($demo->fresh());

        $entry = CompSubmission::where('comp_round_id', $round->id)
            ->where('uploaded_demo_id', $demo->id)
            ->first();

        if (! $entry) {
            $demo->comps_withdrawn_at = $stamp;
            $demo->saveQuietly();

            Notification::make()
                ->danger()
                ->title('The guard would not take it back.')
                ->body('The run is older than the round, or it is on the wrong map or physics. Nothing was changed.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Back in the round.')
            ->body('Entry #' . $entry->id . ': ' . $entry->status . ($entry->invalid_reason ? ' - ' . $entry->invalid_reason : ''))
            ->send();
    }

    /** `pmove_fixed=0, com_maxfps=333`, or null when the demo is clean. */
    private function rawNotes(?UploadedDemo $demo): ?string
    {
        $validity = (array) ($demo?->validity ?? []);

        if (! $validity) {
            return null;
        }

        return implode(', ', array_map(
            fn ($key, $value) => $key . '=' . $value,
            array_keys($validity),
            $validity
        ));
    }

    /**
     * The nick with its Quake colour codes rendered, the way the rest of the
     * admin shows a name. Grouping still happens on the raw string, so two
     * spellings of the same colours never split into two people.
     */
    public function nick(string $name): string
    {
        return UserResource::q3tohtml($name);
    }

    /** m:ss.mmm, the way every other comps screen prints a time. */
    public function formatTime(?int $ms): string
    {
        if (! $ms) {
            return '-';
        }

        return gmdate('i:s', intdiv($ms, 1000)) . '.' . str_pad((string) ($ms % 1000), 3, '0', STR_PAD_LEFT);
    }
}
