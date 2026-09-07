<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

use App\Models\RecordNotification;
use App\Models\Notification;
use App\Models\Announcement;
use Illuminate\Support\Facades\Cache;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * User::$hidden keeps the email out of every payload, because a User gets
     * serialized into public pages wherever it hangs off something else - a
     * demo's uploader, a record's owner - and those pages are readable by
     * anyone. A person's own address is theirs to see, though, and the settings
     * page reads it from `auth.user`, which Jetstream builds from
     * `$request->user()->toArray()`. Unhiding it on that one instance here, in
     * handle(), puts it back before any prop is resolved without widening what
     * the other User objects in the payload expose.
     */
    public function handle($request, \Closure $next)
    {
        $request->user()?->makeVisible('email');

        return parent::handle($request, $next);
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        $recordsNotifications = [];
        $systemNotifications = [];
        $aliases = [];

        if ($request->user()) {
            $user = $request->user();

            // Load user aliases (include MDD aliases via mdd_id)
            $aliasQuery = \App\Models\UserAlias::where('user_id', $user->id);
            if ($user->mdd_id) {
                $aliasQuery = \App\Models\UserAlias::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('mdd_id', $user->mdd_id);
                });
            }
            $aliases = $aliasQuery->orderBy('usage_count', 'desc')->orderBy('created_at', 'desc')
                ->get(['id', 'alias', 'alias_colored', 'usage_count', 'source', 'is_approved', 'created_at']);

            // Filter record notifications based on preview_records setting
            if ($user->preview_records !== 'none') {
                $recordsQuery = RecordNotification::where('read', false)
                    ->where('user_id', $user->id);

                // If preview_records is 'wr', only show world records
                if ($user->preview_records === 'wr') {
                    $recordsQuery->where('worldrecord', true);
                }

                $recordsNotifications = $recordsQuery->orderBy('created_at', 'DESC')->get();
            }

            // What the header ticker shows, out of what the bell already has.
            //
            // Muting is the wrong way round on purpose. The old version built
            // a list of ALLOWED types and filtered to it, so a notification
            // type that belonged to no category on this list simply never
            // reached the header - and nothing said so. `wish_done` was
            // invisible up there from the day it shipped, `alias_suggestion`
            // for far longer: somebody suggests an alias for your profile and
            // the only place it appears is a page you have to already know
            // about.
            //
            // Turning it into a mute list makes the default "show it". A
            // category the user unticked is hidden; a type nobody has filed
            // under a category yet is shown, which is the safer way for this
            // to be wrong. Adding a real setting for one is then a choice, not
            // a thing you have to remember or it disappears.
            //
            // It also fixes the case where the setting meant the opposite of
            // itself: with every category unticked the old allow-list came out
            // empty, the filter was skipped as "nothing to filter by", and the
            // header showed EVERYTHING to the one person who had asked for
            // none of it.
            $previewSystem = $user->preview_system ?? ['announcement', 'clan', 'tournament', 'render', 'map'];

            $systemQuery = Notification::where('read', false)
                ->where('user_id', $user->id);

            $categories = [
                'announcement' => ['announcement'],
                'clan' => [
                    'clan_invite', 'clan_kick', 'clan_accept', 'clan_leave',
                    'clan_transfer', 'clan_request', 'clan_request_accept', 'clan_request_reject',
                ],
                'tournament' => ['tournament_start', 'round_start', 'round_end'],
                'render' => ['render_completed'],
                'map' => ['new_map'],
            ];

            $muted = [];

            foreach ($categories as $category => $types) {
                if (! in_array($category, $previewSystem)) {
                    $muted = array_merge($muted, $types);
                }
            }

            if ($muted) {
                $systemQuery->whereNotIn('type', $muted);
            }

            // Announcement headlines are read back through the announcement
            // they came from, which is two queries for the whole list instead
            // of one per row. English reads the stored copy and needs neither.
            if (app()->getLocale() !== 'en') {
                $systemQuery->with('announcement.translations');
            }

            $systemNotifications = $systemQuery->orderBy('created_at', 'DESC')->get();
        }

        $shared = parent::share($request);

        return array_merge($shared, [
            'recordsNotifications'      =>      $recordsNotifications,
            'systemNotifications'       =>      $systemNotifications,
            'aliases'                   =>      $aliases,
            'danger'                    =>      $request->session()->get('danger'),
            'success'                   =>      $request->session()->get('success'),
            'dangerRandom'                 =>      random_int(0, 1_000_000_000),
            'successRandom'                 =>      random_int(0, 1_000_000_000),
            'canReportDemos'            =>      $request->user() ? (\App\Models\Record::where('user_id', $request->user()->id)->count() >= 30) : false,
            // Attributing somebody else's demo to their account is a public
            // claim about them, so it sits with the people who already answer
            // for the leaderboard. Mirrors DemosController::canAssignDemoToUser.
            'canAssignDemoToUser'       =>      $request->user() ? ((bool) $request->user()->admin || (bool) $request->user()->is_moderator) : false,
            'canUploadDemos'            =>      $request->user() ? $request->user()->canUploadDemos() : true,
            'isVerified'                =>      $request->user() ? $request->user()->hasVerifiedEmail() : false,
            'recordsCount'              =>      $request->user() ? \App\Models\Record::where('user_id', $request->user()->id)->count() : 0,
            'physicsOrder'              =>      $request->user()?->default_physics_order ?? 'vq3_first',
            'canViewRatingBreakdown'    =>      $request->user() ? ($request->user()->admin || (is_array($request->user()->moderator_permissions) && in_array('rating_breakdown', $request->user()->moderator_permissions))) : false,
            'dateFormat'                =>      $request->user()?->global_profile_preferences['date_format'] ?? 'dmY',
            // Separator before the milliseconds in a run time. 'dot' is the
            // default since a wish asked for it; 'colon' is what the engine
            // prints, kept for whoever chose it.
            'timeFormat'                =>      $request->user()?->global_profile_preferences['time_format'] ?? 'dot',
            // Only the code travels in the payload. The strings themselves are
            // a lazily loaded chunk on the frontend, because shipping a few
            // thousand of them with every single Inertia response would cost
            // more than the whole feature is worth.
            'locale'                    =>      app()->getLocale(),
            'locales'                   =>      config('locales.supported'),
            'availableBadges'           =>      $request->user() ? $this->getAvailableBadges($request->user()) : [],
            'globalLatestAnnouncement'  =>      !$request->user() ? Cache::remember('global:latest_announcement', 300, function () {
                return Announcement::where('type', 'home')->orderBy('created_at', 'DESC')->first(['id', 'title', 'created_at']);
            }) : null,
            'defragliveContest'         =>      Cache::remember('defraglive:current_contest', 60, function () {
                $contest = \App\Models\DefragliveContest::current()->first();

                return $contest ? [
                    'id' => $contest->id,
                    'title' => $contest->title,
                    'prize_amount' => $contest->prize_amount,
                    'prize_currency' => $contest->prize_currency,
                    'ends_at' => $contest->ends_at?->toIso8601String(),
                ] : null;
            }),
            'compsBanner'               =>      Cache::remember('comps:banner', 60, fn () => $this->compsBanner()),
        ]);
    }

    /**
     * What the weekly is doing right now, small enough to ride along with every
     * page: what is being played or voted on, and what it pays.
     *
     * There is money on the table every week and the servers page is where
     * people actually are, so it has somewhere to say so. Cached with the
     * contest for the same reason - this rides on every render and the answer
     * changes twice a week.
     */
    private function compsBanner(): ?array
    {
        $round = $this->weeklyRound('active') ?? $this->weeklyRound('voting');

        if (! $round) {
            return null;
        }

        $eur = round(app(\App\Services\Comps\PrizeFunding::class)->forRound($round), 2);

        return [
            'round_id' => $round->id,
            'number' => (int) ($round->comp?->number ?? 0),
            'state' => $round->status,
            'per_physics' => $eur,
            // Both physics are paid, so the week is worth twice the per-physics
            // figure - the same total the comps page leads with.
            'total' => round($eur * count(\App\Services\Comps\BallotResolver::PHYSICS), 2),
            // A round being played runs out; a ballot closes and the round it
            // decides starts at that moment. Two different columns, one
            // countdown on the card.
            'until' => ($round->isVoting() ? $round->voting_closes_at : $round->ends_at)?->toIso8601String(),
            'maps' => $round->isVoting()
                ? []
                : $round->maps->mapWithKeys(fn ($m) => [$m->physics => $m->map?->name])->filter()->all(),
        ];
    }

    /** The one weekly round in the given state, if there is one. */
    private function weeklyRound(string $status): ?\App\Models\CompRound
    {
        return \App\Models\CompRound::where('status', $status)
            ->whereHas('comp', fn ($q) => $q->where('type', \App\Models\Comp::WEEKLY))
            ->with(['comp:id,number', 'maps.map:id,name'])
            ->orderBy('starts_at')
            ->first();
    }

    private function getAvailableBadges($user): array
    {
        $badges = [];

        if ($user->admin) $badges[] = 'badge_admin';
        if ($user->is_moderator && !$user->admin) $badges[] = 'badge_moderator';
        if ($user->isDonor()) $badges[] = 'badge_donor';

        $communityScore = \App\Models\CommunityHelperScore::where('user_id', $user->id)->first();
        if ($communityScore && $communityScore->total_score > 0) $badges[] = 'badge_community';

        if ($user->getTagCount() > 0) $badges[] = 'badge_tagger';

        $demoCounts = $user->getAssignedDemoCounts();
        if (($demoCounts['offline'] + $demoCounts['online']) > 0) $badges[] = 'badge_assigner';

        if (\App\Models\PlayerRating::where('mdd_id', $user->mdd_id)->exists()) $badges[] = 'player_rank';

        if ($communityScore && $communityScore->rank) $badges[] = 'community_rank';

        // Always available
        $badges[] = 'clan';
        $badges[] = 'wr_counters';
        $badges[] = 'socials';

        return $badges;
    }
}
