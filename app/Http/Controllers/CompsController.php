<?php

namespace App\Http\Controllers;

use App\Models\Comp;
use App\Models\User;
use App\Models\CompCandidate;
use App\Models\CompDemoReport;
use App\Models\CompPayout;
use App\Models\CompResult;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\CompVote;
use App\Models\CompWildcard;
use App\Services\Comps\BallotResolver;
use App\Services\Comps\RoundDemoArchive;
use App\Services\Comps\CandidateSelector;
use App\Services\Comps\CompPreviewService;
use App\Services\Comps\CompSettings;
use App\Services\Comps\PrizeFunding;
use App\Services\Comps\ResultsCalculator;
use App\Services\Comps\SubmissionIntake;
use App\Services\Comps\UploadGuard;
use App\Services\Comps\WildcardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Comps: the competition that runs itself.
 *
 * Every week the site draws five maps in a rotating category, everyone votes
 * on them in each physics, and the winners are played for a week. Nobody
 * organises it and nobody can forget to.
 *
 * Two things here are load-bearing and worth stating plainly.
 *
 * Times are hidden while a round is being played. A live leaderboard of route
 * times would hand every later entrant the answer, so the standings show who
 * has entered and nothing else until the round closes.
 *
 * Demos are never downloadable during a round, and are only ever entries
 * because somebody uploaded them here. Serverdemos are collected from bundle
 * servers automatically and comps does not read them at all.
 */
class CompsController extends Controller
{
    public function __construct(
        private CompPreviewService $previews,
        private WildcardService $wildcards,
        private ResultsCalculator $results,
        private UploadGuard $guard,
    ) {
    }

    /**
     * The hub: what is being played, what is being voted on, and who has won
     * before.
     */
    public function index(Request $request)
    {
        $playing = $this->roundWithStatus('active');
        $voting = $this->roundWithStatus('voting') ?? $this->roundWithStatus('locked');

        return Inertia::render('Comps/Index', [
            'prize' => $this->prize($voting ?? $playing),
            'funders' => $this->funders(),
            'playing' => $playing ? $this->playingPayload($playing, $request) : null,
            'voting' => $voting ? $this->votingPayload($voting, $request) : null,
            'history' => $this->history(),
            'leaderboard' => $this->leaderboard(),
            'me' => $request->user() ? $this->myStanding($request->user()->id) : null,
            // Demos of theirs comps is holding back without having entered
            // them. Outside `playing` on purpose: a demo can be on hold for a
            // map that is still being voted on, which is a week when there may
            // be no round being played at all.
            'myNotices' => $request->user() ? $this->guard->noticesFor($request->user()->id) : [],
            'pointsTable' => ResultsCalculator::POINTS,
            'pointsForFinishing' => ResultsCalculator::POINTS_FOR_FINISHING,
            'winsPerWildcard' => CompWildcard::WEEKLY_WINS_REQUIRED,
            'betaNotice' => app(CompSettings::class)->betaNotice(),
            // Where "tell the admin" goes. Built here rather than in the page
            // so the id is not a literal sitting in a Vue file.
            'adminUrl' => route('profile.index', app(CompSettings::class)->contactUserId()),
        ]);
    }

    /**
     * What a weekly pays, and who is paying for it.
     *
     * The amount belongs to the round rather than to the site: somebody
     * donating towards one particular week has to be able to raise that week
     * without raising every week after it, and without rewriting what the
     * weeks before it paid. A round stamps the current default when it is
     * created and keeps it; only a round created before the column existed
     * falls back to the setting.
     *
     * Who is paying is no longer written here as a promise. It used to say the
     * admin funds the first N weeks, which is a sentence the page has to keep
     * repeating and which never expires on its own - it sits there after the
     * money stops until somebody remembers to switch it off. The list of
     * donors says the same thing as a fact, and when a donation's weeks run out
     * it simply stops covering later ones.
     */
    private function prize(?CompRound $round = null): array
    {
        $funding = app(PrizeFunding::class);

        // A round being played is quoted from its own stamp, because that is
        // what its players were told. A round that has not started yet is
        // quoted from the pool as it stands, so money donated today raises
        // next week instead of leaving it advertising the flat rate it
        // happened to be created with. See PrizeFunding::forRound.
        $eur = $round
            ? $funding->forRound($round)
            : $funding->perPhysicsFor(((int) Comp::weekly()->max('number')) + 1);

        return [
            'eur' => $this->money($eur),
            // Both physics are paid, so the week costs twice the per-physics
            // figure. The total is what a reader wants first and the
            // per-physics figure is what they need to not misread it.
            'total' => $this->money($eur * count(BallotResolver::PHYSICS)),
        ];
    }

    /**
     * A money figure the page can print as-is.
     *
     * Spreading a lump sum over a number of weeks that does not divide it
     * gives halves - 150 over ten weeks is 7.50 a physics - so the amounts are
     * no longer whole. But most of them still are, and shipping `5.0` to a
     * page that used to say `5` would put a decimal point on every ordinary
     * week to accommodate the rare one.
     */
    private function money(float $value): int|float
    {
        $rounded = round($value, 2);

        return $rounded == (int) $rounded ? (int) $rounded : $rounded;
    }

    /**
     * Who has put money into the pool, for the panel that names them.
     *
     * People give to this in public and the least it can do is say so. It also
     * answers the question the prize figure raises on its own - a week paying
     * 30 EUR instead of 10 looks arbitrary until you can see who paid for it
     * and for how long.
     */
    private function funders(): ?array
    {
        $funding = app(PrizeFunding::class);
        $donors = $funding->donors();

        if (! $donors) {
            return null;
        }

        return [
            'donors' => $donors,
            'total' => $this->money($funding->totalDonated()),
            // The union of everybody's spans, not the sum: two people funding
            // weeks 3-12 have funded ten weeks between them, not twenty.
            'weeks' => $funding->fundedWeekCount(),
            'funded_through' => $funding->fundedThroughComp(),
        ];
    }

    /** A finished comp, opened from the history list. */
    public function show(Comp $comp)
    {
        abort_unless($comp->status === 'finished', 404);

        $comp->load(['rounds.maps.map', 'rounds.results.user', 'rounds.candidates.map']);

        return Inertia::render('Comps/Show', [
            'comp' => [
                'id' => $comp->id,
                'title' => $comp->title,
                'type' => $comp->type,
                'starts_at' => $comp->starts_at,
                'ends_at' => $comp->ends_at,
                'rounds' => $comp->rounds->map(fn (CompRound $r) => [
                    'id' => $r->id,
                    'index' => $r->index,
                    'category' => $r->category,
                    'weapon' => $r->weapon,
                    'starts_at' => $r->starts_at,
                    'ends_at' => $r->ends_at,
                    'maps' => $r->maps->mapWithKeys(fn ($m) => [$m->physics => [
                        'name' => $m->map?->name,
                        'thumbnail' => $m->map?->thumbnail,
                        'author' => $m->map?->author,
                        'decided_by' => $m->decided_by,
                    ]]),
                    // The week's demos as one download per physics, in two
                    // flavours. Only for a round whose standings are frozen:
                    // until then the demos are private by design.
                    'demos' => $this->demoArchivesFor($r),
                    // What the week paid, per physics. A finished round that
                    // does not say what was at stake reads like a scoreboard
                    // from a friendly.
                    'prize_eur' => $r->prize_eur,
                    // The ballot as it finished: every map that was on it and
                    // what it got. The winner alone says nothing about whether
                    // it was a landslide or one vote.
                    'ballot' => $r->candidates->map(fn (CompCandidate $c) => [
                        'map' => $c->map?->name,
                        'blocked_physics' => $c->blocked_physics,
                        'votes' => ['cpm' => $c->votes_cpm, 'vq3' => $c->votes_vq3],
                    ])->values(),
                    // Who overruled the vote, if anybody did.
                    'wildcards' => $this->wildcardsSpentOn($r),
                    'results' => $this->resultsPayload($r),
                ]),
            ],
        ]);
    }

    /**
     * Cast or move a vote. One per person per physics, changeable right up to
     * the deadline - a vote is a preference, not a commitment.
     */
    public function vote(Request $request, CompRound $round)
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'physics' => ['required', 'in:cpm,vq3'],
        ]);

        $this->assertMayVote($request);

        abort_unless($round->isVoting() && $round->voting_closes_at->isFuture(), 403, __('Voting for this round is closed.'));

        $candidate = CompCandidate::where('comp_round_id', $round->id)
            ->findOrFail($data['candidate_id']);

        abort_unless($candidate->votableIn($data['physics']), 403, __('That map cannot be finished in this physics.'));

        DB::transaction(function () use ($request, $round, $candidate, $data) {
            $existing = CompVote::where('comp_round_id', $round->id)
                ->where('user_id', $request->user()->id)
                ->where('physics', $data['physics'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->comp_candidate_id === $candidate->id) {
                    return;
                }

                CompCandidate::where('id', $existing->comp_candidate_id)
                    ->decrement($this->voteColumn($data['physics']));

                $existing->delete();
            }

            CompVote::create([
                'comp_round_id' => $round->id,
                'comp_candidate_id' => $candidate->id,
                'user_id' => $request->user()->id,
                'physics' => $data['physics'],
            ]);

            $candidate->increment($this->voteColumn($data['physics']));
        });

        return back();
    }

    /**
     * Spend a wildcard. First one in decides the round; anybody else holding
     * one keeps theirs for another week and is told why.
     */
    public function useWildcard(Request $request, CompRound $round)
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'physics' => ['required', 'in:cpm,vq3'],
        ]);

        $this->assertMayVote($request);

        $wildcard = $this->wildcards->heldBy($request->user()->id);

        abort_unless($wildcard, 403, __('You do not hold a wildcard.'));

        $candidate = CompCandidate::where('comp_round_id', $round->id)
            ->findOrFail($data['candidate_id']);

        try {
            $this->wildcards->spend($wildcard, $round, $candidate, $data['physics']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wildcard' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Where the reader stands in comps: what they hold, what they have won,
     * and how close the next wildcard is.
     *
     * The wildcard was invisible until this existed - the only sign you had one
     * was a button appearing on the ballot, which is no use in the six days a
     * week when there is nothing to spend it on and no help at all in telling
     * you that four more weekly wins earns another.
     */
    private function myStanding(int $userId): array
    {
        $wildcards = CompWildcard::where('user_id', $userId)->get();

        $out = [
            'held' => [],
            'spent' => [],
            'wins' => [],
            'wins_to_next' => [],
            'rounds_entered' => 0,
            'average_rank' => null,
            'best_rank' => null,
        ];

        $finishedWeeklyRounds = CompRound::query()
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY)->where('status', 'finished'))
            ->pluck('id');

        foreach (BallotResolver::PHYSICS as $physics) {
            $ofPhysics = $wildcards->where('physics', $physics);

            $out['held'][$physics] = $ofPhysics->whereNull('used_at')->count();
            $out['spent'][$physics] = $ofPhysics->whereNotNull('used_at')->count();

            $wins = CompResult::where('user_id', $userId)
                ->where('physics', $physics)
                ->winners()
                ->whereIn('comp_round_id', $finishedWeeklyRounds)
                ->count();

            $out['wins'][$physics] = $wins;

            // How many more weekly wins earn the next one. Counted off the
            // running total rather than from the last award, so it stays right
            // however the wins are spread across a year.
            $needed = CompWildcard::WEEKLY_WINS_REQUIRED;
            $out['wins_to_next'][$physics] = $needed - ($wins % $needed);
        }

        $ranks = CompResult::where('user_id', $userId)->pluck('rank');

        if ($ranks->isNotEmpty()) {
            $out['rounds_entered'] = $ranks->count();
            $out['average_rank'] = round($ranks->avg(), 1);
            $out['best_rank'] = (int) $ranks->min();
        }

        return $out;
    }

    /** Which round is at a given stage. Weekly only ever has one at a time. */
    private function roundWithStatus(string $status): ?CompRound
    {
        return CompRound::where('status', $status)
            ->whereHas('comp', fn ($q) => $q->where('type', Comp::WEEKLY))
            ->with(['comp', 'candidates.map', 'maps.map'])
            ->orderBy('starts_at')
            ->first();
    }

    private function votingPayload(CompRound $round, Request $request): array
    {
        $previews = $this->previews->forRound($round);
        $user = $request->user();

        $myVotes = $user
            ? CompVote::where('comp_round_id', $round->id)
                ->where('user_id', $user->id)
                ->pluck('comp_candidate_id', 'physics')
            : collect();

        // One boolean, not one per physics: a wildcard is spendable on
        // either ballot now, so holding one lights up both.
        $holdsWildcard = $user ? (bool) $this->wildcards->heldBy($user->id) : false;

        $wildcardsHeld = [];
        foreach (BallotResolver::PHYSICS as $physics) {
            $wildcardsHeld[$physics] = $holdsWildcard;
        }

        $isOpen = $round->isVoting() && $round->voting_closes_at->isFuture();

        return [
            'round_id' => $round->id,
            'comp_title' => $round->comp->title,
            'category' => $round->category,
            'weapon' => $round->weapon,
            'closes_at' => $round->voting_closes_at,
            'starts_at' => $round->starts_at,
            // What the week being voted on will pay. It belongs next to the
            // ballot, not only in the header block: this is the week you are
            // choosing a map for, and it may not pay what the current one does.
            'prize' => $this->prize($round),
            // The category the week after this one will run. The rotation is
            // fixed and known years ahead, so there is no reason to make
            // people wait a week to find out - and a combo week coming is
            // worth knowing about while you are still picking a strafe map.
            //
            // Only the category, never the gun: a weapon week draws its gun
            // when the round is created, so naming one now would be inventing
            // it.
            'next_category' => app(CandidateSelector::class)
                ->categoryForWeekly((int) $round->comp->number + 1),
            'is_open' => $isOpen,
            'decided' => $round->maps->mapWithKeys(fn ($m) => [$m->physics => [
                'map' => $m->map?->name,
                'decided_by' => $m->decided_by,
            ]]),
            'candidates' => $round->candidates->map(fn (CompCandidate $c) => [
                'id' => $c->id,
                'map_id' => $c->map_id,
                'map' => $c->map?->name,
                'thumbnail' => $c->map?->thumbnail,
                'author' => $c->map?->author,
                'blocked_physics' => $c->blocked_physics,
                // Not while the ballot is open, and absent rather than zero.
                // A running count says which map is going to win, and a week
                // is long enough to grind it before it is even chosen. They
                // come back the moment voting closes, which is when they stop
                // being a head start and become the result. Left out of the
                // payload rather than hidden in the page, because a number
                // that is sent is a number anybody can read.
                'votes' => $isOpen ? null : ['cpm' => $c->votes_cpm, 'vq3' => $c->votes_vq3],
                'preview' => $previews[$c->map_id] ?? null,
            ])->values(),
            'my_votes' => $myVotes,
            'wildcards_held' => $wildcardsHeld,
            'may_vote' => $this->mayVote($request),
        ];
    }

    /**
     * The ballot that chose this round's maps, as it finished.
     *
     * The same shape the ballot itself has - every map with what it got in
     * both physics - rather than one list per physics. It is the one picture
     * of the week's vote and it reads as one: a map that lost cpm by two and
     * won vq3 by eight says something neither half says alone.
     *
     * A map barred from a physics gets null there rather than a zero it never
     * had a chance to beat, which is the rule the history page follows.
     *
     * @return array{totals:array<string,int>, rows:array<int, array<string, mixed>>}
     */
    private function settledBallot(CompRound $round): array
    {
        $round->loadMissing('candidates.map', 'maps');

        $winners = [];
        $totals = [];

        foreach (BallotResolver::PHYSICS as $physics) {
            $winners[$physics] = $round->maps->firstWhere('physics', $physics)?->map_id;
            $totals[$physics] = 0;
        }

        $rows = $round->candidates->map(function (CompCandidate $c) use ($winners, &$totals) {
            $votes = [];
            $won = [];

            foreach (BallotResolver::PHYSICS as $physics) {
                if ($c->blocked_physics === $physics) {
                    $votes[$physics] = null;
                    $won[$physics] = false;

                    continue;
                }

                $votes[$physics] = (int) $c->{$this->voteColumn($physics)};
                $won[$physics] = $c->map_id === $winners[$physics];
                $totals[$physics] += $votes[$physics];
            }

            return [
                'map' => $c->map?->name,
                'thumbnail' => $c->map?->thumbnail,
                'author' => $c->map?->author,
                'blocked_physics' => $c->blocked_physics,
                'votes' => $votes,
                'won' => $won,
            ];
        })
            // Most voted first, counting both physics: the ballot is one
            // picture and the map that carried the week leads it.
            ->sortByDesc(fn ($r) => array_sum(array_map(fn ($v) => $v ?? 0, $r['votes'])))
            ->values()
            ->all();

        return ['totals' => $totals, 'rows' => $rows];
    }

    private function playingPayload(CompRound $round, Request $request): array
    {
        $user = $request->user();

        return [
            'round_id' => $round->id,
            'comp_title' => $round->comp->title,
            'category' => $round->category,
            'weapon' => $round->weapon,
            'ends_at' => $round->ends_at,
            // What this round is being played for. The page used to print a
            // prize on the ballot and none on the round being run, which read
            // as the money belonging to the voting rather than to the race.
            'prize' => $this->prize($round),
            'maps' => $round->maps->mapWithKeys(fn ($m) => [$m->physics => [
                'name' => $m->map?->name,
                'thumbnail' => $m->map?->thumbnail,
                'author' => $m->map?->author,
                'decided_by' => $m->decided_by,
            ]]),
            // How each map got here. It used to be visible only while the
            // round sat locked between the ballot closing and play starting,
            // so whoever missed that window never learned what the vote said
            // - and with no lead configured that window does not exist at
            // all. It belongs on the map being played: that is where somebody
            // asks why this map, and voting is long over, so the count gives
            // nothing away.
            'ballot' => $this->settledBallot($round),
            // Times stay hidden until the round closes, so this is who has
            // entered rather than who is winning.
            'entrants' => $this->entrants($round),
            'removed_entrants' => $this->removedEntrants($round),
            'my_entries' => $user ? $this->myEntries($round, $user->id) : [],
            'entry_gate' => $this->entryGate($request),
        ];
    }

    /**
     * Whether the person looking may enter a run at all, and what to say if
     * not.
     *
     * A code as well as a sentence, because two of the three refusals have
     * somewhere to go - sign in, or link the profile in settings - and the
     * page has to know which link to put next to which sentence. The rule
     * itself lives in SubmissionIntake, where both upload routes read it.
     */
    private function entryGate(Request $request): array
    {
        $user = $request->user();
        $reason = app(SubmissionIntake::class)->userRejectionReason($user);

        return [
            'may' => $reason === null,
            'reason' => $reason,
            'needs' => match (true) {
                $reason === null => null,
                ! $user => 'signin',
                ! $user->hasVerifiedEmail() => 'verify',
                default => 'mdd',
            },
        ];
    }

    /**
     * Who has entered, per physics, without saying how fast. Enough for people
     * to see the round is alive; not enough to tell anybody what to beat.
     */
    private function entrants(CompRound $round): array
    {
        $rows = CompSubmission::query()
            ->counting()
            ->where('comp_round_id', $round->id)
            ->with('user:id,name,country,profile_photo_path,name_effect,color')
            ->get()
            ->groupBy('physics');

        $out = [];

        foreach (BallotResolver::PHYSICS as $physics) {
            $out[$physics] = ($rows->get($physics) ?? collect())
                ->unique('user_id')
                ->map(fn (CompSubmission $s) => [
                    'id' => $s->user?->id,
                    'name' => $s->user?->name,
                    'country' => $s->user?->country,
                    'photo' => $s->user?->profile_photo_path,
                    'name_effect' => $s->user?->name_effect,
                    'color' => $s->user?->color,
                ])
                ->values();
        }

        return $out;
    }

    /**
     * People whose run an admin took out, shown alongside the entrants rather
     * than deleted from the list.
     *
     * Silently dropping them would make the round page read as though they
     * never turned up, which is both unfair to them and confusing to everyone
     * who watched them enter. Only admin removals appear here: a run the
     * validator refused - wrong map, unreadable file - is somebody picking the
     * wrong demo, and publishing that would be publishing their slip.
     *
     * Anybody who still has a counting run in that physics is left out: they
     * had one entry pulled and another accepted, and they are simply an
     * entrant.
     */
    private function removedEntrants(CompRound $round): array
    {
        $rows = CompSubmission::query()
            ->removedByAdmin()
            ->where('comp_round_id', $round->id)
            ->whereNotNull('physics')
            ->with('user:id,name,country,profile_photo_path,name_effect,color')
            ->get()
            ->groupBy('physics');

        $stillIn = CompSubmission::query()
            ->counting()
            ->where('comp_round_id', $round->id)
            ->get()
            ->groupBy('physics')
            ->map(fn ($g) => $g->pluck('user_id')->all());

        $out = [];

        foreach (BallotResolver::PHYSICS as $physics) {
            $keep = $stillIn->get($physics, []);

            $out[$physics] = ($rows->get($physics) ?? collect())
                ->reject(fn (CompSubmission $s) => in_array($s->user_id, $keep, true))
                ->unique('user_id')
                ->map(fn (CompSubmission $s) => [
                    'id' => $s->user?->id,
                    'name' => $s->user?->name,
                    'country' => $s->user?->country,
                    'photo' => $s->user?->profile_photo_path,
                    'name_effect' => $s->user?->name_effect,
                    'color' => $s->user?->color,
                    'reason' => $s->invalid_reason,
                ])
                ->values();
        }

        return $out;
    }

    /** Your own entries, times and all. Yours are never a secret from you. */
    private function myEntries(CompRound $round, int $userId): array
    {
        $entries = CompSubmission::where('comp_round_id', $round->id)
            ->where('user_id', $userId)
            ->with(['demo' => fn ($q) => $q->withUnreleasedComps()])
            ->orderByRaw('physics IS NULL')
            ->orderBy('physics')
            ->orderBy('time')
            ->get();

        // Refused entries can be asked about, and an entry already asked about
        // says so rather than offering the same button a second time.
        $asked = CompDemoReport::whereIn('uploaded_demo_id', $entries->pluck('uploaded_demo_id')->filter())
            ->where('reported_by', $userId)
            ->pluck('uploaded_demo_id')
            ->all();

        return $entries
            ->map(fn (CompSubmission $s) => [
                'id' => $s->id,
                'physics' => $s->physics,
                'time' => $s->time,
                // Online is the gametype the run was made in, not whether we
                // paired it with a record. The pairing is the separate, and
                // purely decorative, fact below it.
                'is_online' => (bool) $s->is_online,
                'gametype' => $s->demo?->gametype,
                'matched_record' => $s->matched_record_id !== null,
                'is_highlight' => $s->is_highlight,
                'status' => $s->status,
                'reason' => $s->invalid_reason,
                'filename' => $s->demo?->original_filename,
                'demo_id' => $s->uploaded_demo_id,
                'reported' => in_array($s->uploaded_demo_id, $asked, true),
            ])
            ->all();
    }

    /**
     * Wildcards spent on a round, keyed by the physics they decided.
     *
     * A round whose map was named rather than voted for says so in one line of
     * grey text, which is a strange way to report the one thing that can
     * overrule everybody. Naming who spent it also makes the right feel like
     * something people hold rather than a rule in the abstract.
     */
    private function wildcardsSpentOn(CompRound $round): array
    {
        return CompWildcard::where('used_on_round_id', $round->id)
            ->whereNotNull('used_at')
            ->with('user:id,name,country,profile_photo_path,name_effect,color')
            ->get()
            ->mapWithKeys(fn (CompWildcard $w) => [$w->used_physics ?? $w->physics => [
                'user' => $w->user ? [
                    'id' => $w->user->id,
                    'name' => $w->user->name,
                    'country' => $w->user->country,
                    'photo' => $w->user->profile_photo_path,
                    'name_effect' => $w->user->name_effect,
                    'color' => $w->user->color,
                ] : null,
            ]])
            ->all();
    }

    private function resultsPayload(CompRound $round): array
    {
        $out = [];
        $payouts = $this->payoutsFor([$round->id]);

        foreach (BallotResolver::PHYSICS as $physics) {
            $out[$physics] = CompResult::where('comp_round_id', $round->id)
                ->where('physics', $physics)
                ->with('user:id,name,country,profile_photo_path,name_effect,color')
                ->orderBy('rank')
                ->orderBy('time')
                ->get()
                ->map(fn (CompResult $r) => [
                    'rank' => $r->rank,
                    'time' => $r->time,
                    'points' => (float) $r->points,
                    // What became of the prize. A finished week that shows
                    // "15 EUR" beside the winner and nothing else reads as
                    // money still owed, whether it was paid or given back.
                    'payout' => $payouts[$physics][$r->user_id] ?? null,
                    'user' => [
                        'id' => $r->user?->id,
                        'name' => $r->user?->name,
                        'country' => $r->user?->country,
                        'photo' => $r->user?->profile_photo_path,
                        'name_effect' => $r->user?->name_effect,
                        'color' => $r->user?->color,
                    ],
                ])
                ->all();
        }

        return $out;
    }

    /** Finished comps, most recent first, with their winners. */
    /**
     * Every finished comp's points added up per player, one table per year
     * and one for all time. The history list says who won each week; this
     * says who has been winning. The points are the ones each result row
     * already carries, the season scale, so a win is 25 and finishing is 1.
     *
     * @return array{periods: list<array{key:string,label:string}>, rows: array<string, list<array>>}
     */
    private function leaderboard(): array
    {
        $rows = CompResult::query()
            ->join('comp_rounds', 'comp_rounds.id', '=', 'comp_results.comp_round_id')
            ->join('comps', 'comps.id', '=', 'comp_rounds.comp_id')
            ->where('comps.status', 'finished')
            ->selectRaw('YEAR(comps.ends_at) as year, comp_results.user_id, comp_results.physics')
            ->selectRaw('COUNT(DISTINCT comps.id) as comps, SUM(comp_results.rank = 1) as wins, SUM(comp_results.points) as points')
            ->groupBy('year', 'comp_results.user_id', 'comp_results.physics')
            ->get();

        $users = User::whereIn('id', $rows->pluck('user_id')->unique())
            ->get(['id', 'name', 'country', 'profile_photo_path', 'name_effect', 'color'])
            ->keyBy('id');

        $tables = [];
        foreach ($rows as $r) {
            foreach ([(string) $r->year, 'all'] as $period) {
                $t = &$tables[$period][$r->user_id];
                $t['comps'] = ($t['comps'] ?? 0) + (int) $r->comps;
                $t['wins'] = ($t['wins'] ?? 0) + (int) $r->wins;
                $t['points_' . $r->physics] = ($t['points_' . $r->physics] ?? 0) + (float) $r->points;
                unset($t);
            }
        }

        $periods = collect(array_keys($tables))->filter(fn ($k) => $k !== 'all')->sortDesc()->values()
            ->map(fn ($y) => ['key' => $y, 'label' => $y])
            ->push(['key' => 'all', 'label' => 'All time'])
            ->all();

        $out = [];
        foreach ($tables as $period => $byUser) {
            $list = collect($byUser)->map(function ($t, $userId) use ($users) {
                $u = $users[$userId] ?? null;
                $cpm = round($t['points_cpm'] ?? 0, 1);
                $vq3 = round($t['points_vq3'] ?? 0, 1);

                return [
                    'id' => $u?->id,
                    'name' => $u?->name,
                    'country' => $u?->country,
                    'photo' => $u?->profile_photo_path,
                    'name_effect' => $u?->name_effect,
                    'color' => $u?->color,
                    // One comp with both physics entered is one comp, so
                    // the per-physics count is capped by the distinct comps.
                    'comps' => $t['comps'],
                    'wins' => $t['wins'],
                    'points_cpm' => $cpm,
                    'points_vq3' => $vq3,
                    'points' => round($cpm + $vq3, 1),
                ];
            })
            ->sortBy([['points', 'desc'], ['wins', 'desc'], ['comps', 'asc'], ['name', 'asc']])
            ->values();

            // Equal points share a rank, as everywhere else in comps.
            $rank = 0; $last = null;
            $out[$period] = $list->map(function ($row, $i) use (&$rank, &$last) {
                if ($row['points'] !== $last) { $rank = $i + 1; $last = $row['points']; }
                $row['rank'] = $rank;
                return $row;
            })->all();
        }

        return ['periods' => $periods, 'rows' => $out];
    }

    private function history(int $limit = 12): array
    {
        $comps = Comp::where('status', 'finished')
            ->orderByDesc('ends_at')
            ->limit($limit)
            ->with('rounds.maps.map')
            ->get();

        return $comps->map(function (Comp $comp) {
            $winners = [];
            $payouts = $this->payoutsFor($comp->rounds->pluck('id')->all());

            foreach (BallotResolver::PHYSICS as $physics) {
                $winners[$physics] = CompResult::whereIn('comp_round_id', $comp->rounds->pluck('id'))
                    ->where('physics', $physics)
                    ->where('rank', '<=', 3)
                    ->orderBy('rank')->orderBy('time')
                    ->with('user:id,name,country,profile_photo_path,name_effect,color')
                    ->get()
                    ->map(fn (CompResult $r) => [
                        'rank' => $r->rank,
                        'id' => $r->user?->id,
                        'name' => $r->user?->name,
                        'country' => $r->user?->country,
                        'photo' => $r->user?->profile_photo_path,
                        'name_effect' => $r->user?->name_effect,
                        'color' => $r->user?->color,
                        'time' => $r->time,
                        'payout' => $payouts[$physics][$r->user_id] ?? null,
                    ])
                    ->values();
            }

            // Weekly has one round; season several. The card shows the first
            // round's category and maps and the detail page shows the rest.
            $first = $comp->rounds->sortBy('index')->first();

            return [
                'id' => $comp->id,
                'title' => $comp->title,
                'type' => $comp->type,
                'starts_at' => $comp->starts_at,
                'ends_at' => $comp->ends_at,
                'category' => $first?->category,
                'weapon' => $first?->weapon,
                'prize_eur' => $first?->prize_eur,
                'rounds' => $comp->rounds->count(),
                'entrants' => CompResult::whereIn('comp_round_id', $comp->rounds->pluck('id'))->count(),
                'entrants_by_physics' => CompResult::whereIn('comp_round_id', $comp->rounds->pluck('id'))
                    ->selectRaw('physics, count(*) as n')->groupBy('physics')->pluck('n', 'physics'),
                'maps' => $comp->rounds->flatMap(fn (CompRound $r) => $r->maps->pluck('map.name'))->unique()->values(),
                'map_by_physics' => ($first?->maps ?? collect())->mapWithKeys(fn ($m) => [$m->physics => [
                    'name' => $m->map?->name,
                    'thumbnail' => $m->map?->thumbnail,
                ]]),
                'winners' => $winners,
            ];
        })->all();
    }

    /**
     * Every counting demo of a finished round's physics, as one 7z.
     *
     * `anonymized` names the files by rank and time only, for anyone who
     * wants to watch the week cold and guess who ran what. `revealed` puts
     * the player's name in every file name.
     */
    public function downloadDemos(CompRound $round, string $physics, string $mode, RoundDemoArchive $archive)
    {
        abort_unless(in_array($physics, BallotResolver::PHYSICS, true), 404);
        abort_unless(in_array($mode, RoundDemoArchive::MODES, true), 404);
        abort_unless($round->demosVisible(), 404);

        $path = $archive->path($round, $physics, $mode);

        abort_if($path === null, 404, __('No demos to download for this physics.'));

        return response()->download($path, $archive->downloadName($round, $physics, $mode), [
            'Content-Type' => 'application/x-7z-compressed',
        ]);
    }

    /**
     * @return array<string, array{count: int, anonymized: string, revealed: string}>
     */
    private function demoArchivesFor(CompRound $round): array
    {
        if (! $round->demosVisible()) {
            return [];
        }

        $archive = app(RoundDemoArchive::class);
        $out = [];

        foreach (BallotResolver::PHYSICS as $physics) {
            $count = $archive->count($round, $physics);

            if ($count === 0) {
                continue;
            }

            $out[$physics] = [
                'count' => $count,
                'anonymized' => route('comps.demos', [$round->id, $physics, RoundDemoArchive::ANONYMIZED]),
                'revealed' => route('comps.demos', [$round->id, $physics, RoundDemoArchive::REVEALED]),
            ];
        }

        return $out;
    }

    /**
     * The settled prizes of the given rounds, keyed by physics and winner.
     *
     * One row per physics per winner, so a tie has one each. The status is
     * the thing shown; the amount comes with it because it was copied onto
     * the row when the week ended, and an admin correcting the round's prize
     * later must not make the page claim somebody was handed more or less
     * than they were.
     *
     * @param  int[]  $roundIds
     * @return array<string, array<int, array{status: string, label: string, amount: string, resolved_at: ?string}>>
     */
    private function payoutsFor(array $roundIds): array
    {
        $out = [];

        foreach (CompPayout::whereIn('comp_round_id', $roundIds)->get() as $payout) {
            $out[$payout->physics][$payout->user_id] = [
                'status' => $payout->status,
                'label' => $payout->label(),
                'amount' => $this->money((float) $payout->amount),
                // status => euro for each way the money went. One entry for
                // a whole-amount settlement, two or three for a split.
                'parts' => array_map(fn ($eur) => $this->money($eur), $payout->parts()),
                'resolved_at' => $payout->resolved_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * Voting needs an account with a linked MDD profile. The prize for winning
     * is a wildcard, so a bare sign-up form would be an invitation to vote with
     * as many accounts as you can be bothered to make.
     */
    private function mayVote(Request $request): bool
    {
        return $request->user() !== null && $request->user()->mdd_id !== null;
    }

    private function assertMayVote(Request $request): void
    {
        abort_unless($request->user(), 403);
        abort_unless(
            $request->user()->mdd_id,
            403,
            __('Link your mDd profile to vote in comps.')
        );
    }

    private function voteColumn(string $physics): string
    {
        return $physics === 'cpm' ? 'votes_cpm' : 'votes_vq3';
    }
}
