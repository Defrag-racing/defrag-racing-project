<?php

namespace App\Services;

use App\Models\DefragliveContest;
use App\Models\DefragliveWatchExclusion;
use App\Models\DefragliveWatchSession;
use App\Models\MddProfile;
use App\Models\OnlinePlayer;
use App\Models\Server;
use App\Models\User;
use App\Models\UserAlias;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Watch-time tracking + contest logic for DefragLive.
 *
 * The bot publishes a serverstate (~every 2.5s) carrying current_player - who
 * it is spectating right now. The server_state table only keeps "now", so we
 * accrue that history here into continuous watch sessions, then a contest is a
 * window over those sessions and the winner is a watch-time-weighted raffle.
 *
 * Identity: the bot payload carries no mdd_id (svinfo doesn't expose it), so the
 * watched player is matched against the live player list, which carries an
 * mdd_id only for someone actually logged in (precise client_id match on the
 * server first, then by clean name). No login behind that nick means no account:
 * the session is kept under the bare name and counts as its own contestant.
 */
class DefragliveWatchService
{
    /**
     * Window (seconds) the public "now watching" indicator treats an open
     * session as live for. The bot emits serverstate only on change, so during
     * a quiet watch there are no updates - we still consider it live well past a
     * tick interval. Purely cosmetic (which session is "current"); it never
     * affects credited time.
     */
    public const LIVE_WINDOW = 600;

    /**
     * How often an account must have played under an alias before that alias may
     * stand in for a login. Above it the name is one the player is known by;
     * below it, it is a name they wore once - frog's "suburb" was used exactly
     * once and cost frog his leaderboard row.
     */
    public const ALIAS_MIN_USES = 10;

    /**
     * Safety net only: a session open longer than this with no update is treated
     * as an orphan from a crashed/stopped bot and closed at its last sighting,
     * so it doesn't credit unbounded time. Set well above any real single watch.
     */
    public const ORPHAN_HOURS = 6;

    /** Seconds of watch time per raffle ticket (1 minute = 1 ticket). */
    public const SECONDS_PER_TICKET = 60;

    /**
     * Fold one serverstate snapshot into the open watch session. Called from the
     * ingest serverstate branch (safe under concurrent POSTs via a row lock).
     *
     * Model: the bot is always spectating exactly one player. Silence just means
     * "still on the same player, nothing changed" - NOT a gap. So a watch runs
     * from when it started until the bot moves to someone else (or goes idle),
     * and that move is always reported by an emit. We credit the previous player
     * the full span up to that moment. No gap cap, no "offline" guessing.
     */
    public function accrue(array $serverstate): void
    {
        $current = $serverstate['current_player'] ?? null;

        // No one being spectated, or the bot is watching itself (idle) -> the
        // current watch stretch is over; close it and credit nobody.
        if (! is_array($current) || $this->isBot($current, $serverstate)) {
            $this->closeOpenSession();

            return;
        }

        $name = (string) ($current['n'] ?? '');
        $clean = $this->cleanName($name);
        if ($clean === '') {
            $this->closeOpenSession();

            return;
        }

        $ip = $serverstate['ip'] ?? null;
        $mapname = $serverstate['mapname'] ?? null;
        $identity = $this->resolve($current, $ip);
        $key = $this->keyFor($identity['mdd_id'], $clean);

        DB::transaction(function () use ($identity, $name, $clean, $ip, $mapname, $key) {
            $open = DefragliveWatchSession::open()->lockForUpdate()->first();

            if ($open) {
                $openKey = $this->keyFor($open->mdd_id, $open->player_name_clean);

                if ($openKey === $key) {
                    // Still the same player: extend the running span to now.
                    $open->seconds = $this->span($open->started_at, now());
                    $open->last_seen_at = now();
                    $open->mapname = $mapname ?: $open->mapname;
                    if (! $open->mdd_id && $identity['mdd_id']) {
                        $open->mdd_id = $identity['mdd_id'];
                        $open->user_id = $identity['user_id'];
                    }
                    $open->save();

                    return;
                }

                // Switched to a different player: the previous one was watched
                // continuously until this very moment, so credit the full span.
                $open->seconds = $this->span($open->started_at, now());
                $open->ended_at = now();
                $open->save();
            }

            $this->openSession($identity, $name, $clean, $ip, $mapname);
        });
    }

    /**
     * Close the currently open session (player switch reported as idle / bot
     * self-spectating). The watch ran continuously until now, so credit the
     * full span to now.
     */
    public function closeOpenSession(): void
    {
        DB::transaction(function () {
            $open = DefragliveWatchSession::open()->lockForUpdate()->first();
            if (! $open) {
                return;
            }

            $open->seconds = $this->span($open->started_at, now());
            $open->ended_at = now();
            $open->save();
        });
    }

    /**
     * Safety net for a crashed/stopped bot only: a session left open for hours
     * with no update is an orphan (the bot is gone, no switch ever came). Close
     * it at its last sighting so it can't credit unbounded time. Normal watches
     * are closed by the next switch/idle, long before this fires.
     */
    public function closeStaleSessions(): int
    {
        $stale = DefragliveWatchSession::open()
            ->where('last_seen_at', '<', now()->subHours(self::ORPHAN_HOURS))
            ->get();

        foreach ($stale as $s) {
            $end = $s->last_seen_at ?? $s->started_at;
            $s->seconds = $this->span($s->started_at, $end);
            $s->ended_at = $end;
            $s->save();
        }

        return $stale->count();
    }

    /** Whole seconds between two timestamps (>= 0). */
    private function span($start, $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        return max(0, (int) $start->diffInSeconds($end));
    }

    private function openSession(array $identity, string $name, string $clean, ?string $ip, ?string $mapname): void
    {
        DefragliveWatchSession::create([
            'mdd_id' => $identity['mdd_id'],
            'user_id' => $identity['user_id'],
            'player_name' => $name,
            'player_name_clean' => $clean,
            'ip' => $ip,
            'mapname' => $mapname,
            'seconds' => 0,
            'started_at' => now(),
            'last_seen_at' => now(),
            'ended_at' => null,
        ]);
    }

    /**
     * Resolve a watched player to [mdd_id, user_id]. Best effort, never throws.
     * Order: precise live client_id on this server -> clean-name against the live
     * player list -> unresolved (name only).
     *
     * Both steps read the same thing: an mdd_id the game itself reported for a
     * player who is logged in right now. A declared alias is NOT that. An alias
     * is a nick a player wrote on their profile, and somebody else can be sitting
     * behind it - frog has "suburb" as an alias, but the suburb the bot spectated
     * was not logged in as frog, so that watch time was never frog's. With no
     * login for the nick the session stays unresolved and counts as its own
     * contestant.
     */
    public function resolve(array $current, ?string $ip): array
    {
        $clean = $this->cleanName((string) ($current['n'] ?? ''));
        $clientId = isset($current['id']) ? (int) $current['id'] : null;
        $mddId = null;

        // 1) Precise: the same client_id on the server with this ip.
        if ($ip && $clientId !== null) {
            $serverId = Server::where('ip', $ip)->value('id');
            if ($serverId) {
                $op = OnlinePlayer::where('server_id', $serverId)
                    ->where('client_id', $clientId)
                    ->whereNotNull('mdd_id')
                    ->first();
                if ($op && $op->mdd_id) {
                    $mddId = (int) $op->mdd_id;
                }
            }
        }

        // 2) Clean-name match against the live player map, and only when the
        //    whole map answers with one account. Nicks are not unique - two
        //    people playing as "kali" at the same time is not hypothetical -
        //    and the second-best guess is somebody else's watch time.
        if (! $mddId && $clean !== '' && ! $this->isDefaultName($clean)) {
            $live = OnlinePlayer::whereNotNull('mdd_id')
                ->get(['name', 'mdd_id'])
                ->filter(fn ($p) => $this->cleanName((string) $p->name) === $clean)
                ->pluck('mdd_id')
                ->unique();
            if ($live->count() === 1) {
                $mddId = (int) $live->first();
            }
        }

        $userId = $mddId ? User::where('mdd_id', $mddId)->value('id') : null;

        return ['mdd_id' => $mddId, 'user_id' => $userId ? (int) $userId : null];
    }

    /**
     * Aggregate watch time per player over a contest window. Returns rows sorted
     * by total seconds desc: ['mdd_id','user_id','name','name_clean','seconds',
     * 'tickets','user']. $limit null = everyone (used by the draw).
     */
    public function leaderboard(DefragliveContest $contest, ?int $limit = 10): array
    {
        $periodStart = $contest->starts_at->copy();
        $periodEnd = $contest->ends_at->copy();
        $now = now();
        $windowEnd = $now->lessThan($periodEnd) ? $now : $periodEnd;

        $rows = DefragliveWatchSession::query()
            // Include every session that overlaps the contest. A continuous
            // watch can start in the previous period or end in the next one.
            ->where('started_at', '<', $periodEnd)
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>', $periodStart);
            })
            ->orderBy('id')
            ->get(['mdd_id', 'user_id', 'player_name', 'player_name_clean', 'seconds', 'started_at', 'ended_at']);

        $cutoffs = $this->exclusionCutoffs();
        $known = $this->identityByCleanName($rows);

        $groups = [];
        foreach ($rows as $r) {
            $mddId = $r->mdd_id ?: ($known[$r->player_name_clean] ?? null);
            $key = $this->keyFor($mddId, $r->player_name_clean);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'mdd_id' => $mddId ? (int) $mddId : null,
                    'user_id' => $r->user_id ? (int) $r->user_id : null,
                    'name' => $r->player_name,
                    'name_clean' => $r->player_name_clean,
                    'seconds' => 0,
                ];
            }
            // Count only the part inside this contest. Using the timestamps
            // avoids credit leaking across rollover boundaries, including for
            // an open session whose stored seconds updates only on an emit.
            $sessionStart = $r->started_at->lessThan($periodStart)
                ? $periodStart
                : $r->started_at;
            // Admin exclusion: anything watched before the ban moment does not
            // count (the effective window start moves up to the cutoff).
            $cutoff = $this->cutoffFor($cutoffs, $mddId, $r->player_name_clean);
            if ($cutoff && $cutoff->greaterThan($sessionStart)) {
                $sessionStart = $cutoff;
            }
            $rawEnd = $r->ended_at ?? $windowEnd;
            $sessionEnd = $rawEnd->greaterThan($windowEnd)
                ? $windowEnd
                : $rawEnd;

            // Guard the clamps: a session lying entirely before the exclusion
            // cutoff (or outside the window) must contribute nothing - span()
            // is absolute-valued and would count a reversed interval.
            if ($sessionStart->lessThan($sessionEnd)) {
                $groups[$key]['seconds'] += $this->span($sessionStart, $sessionEnd);
            }
            // Keep the latest seen colored name / resolved identity. For an
            // account this is only a fallback - accountNames() overrides it
            // below, so a change of nick does not rename the whole record.
            $groups[$key]['name'] = $r->player_name ?: $groups[$key]['name'];
            if ($mddId) {
                $groups[$key]['mdd_id'] = (int) $mddId;
            }
            if ($r->user_id) {
                $groups[$key]['user_id'] = (int) $r->user_id;
            }
        }

        $entries = array_values($groups);
        usort($entries, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);

        if ($limit !== null) {
            $entries = array_slice($entries, 0, $limit);
        }

        // Attach a lightweight resolved user for display / profile links.
        $userIds = array_values(array_filter(array_column($entries, 'user_id')));
        $users = $userIds
            ? User::whereIn('id', $userIds)
                ->get(['id', 'name', 'plain_name', 'profile_photo_path', 'country', 'mdd_id'])
                ->keyBy('id')
            : collect();

        $accountNames = $this->accountNames($entries);

        foreach ($entries as &$e) {
            $e['tickets'] = intdiv($e['seconds'], self::SECONDS_PER_TICKET);
            $e['user'] = $e['user_id'] ? $users->get($e['user_id']) : null;
            $e['name'] = $accountNames[$e['mdd_id']] ?? $e['name'];
        }

        return $entries;
    }

    /**
     * All-time watch totals across EVERY recorded session (no contest window),
     * grouped by the same identity rules as leaderboard(). Powers the public
     * page's expandable "all-time stats" view; $limit = null returns everyone.
     */
    public function allTimeLeaderboard(?int $limit = null): array
    {
        $rows = DefragliveWatchSession::query()
            ->orderBy('id')
            ->get(['mdd_id', 'user_id', 'player_name', 'player_name_clean', 'seconds', 'started_at', 'ended_at']);

        $cutoffs = $this->exclusionCutoffs();
        $known = $this->identityByCleanName($rows);

        $groups = [];
        foreach ($rows as $r) {
            $mddId = $r->mdd_id ?: ($known[$r->player_name_clean] ?? null);
            $key = $this->keyFor($mddId, $r->player_name_clean);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'mdd_id' => $mddId ? (int) $mddId : null,
                    'user_id' => $r->user_id ? (int) $r->user_id : null,
                    'name' => $r->player_name,
                    'seconds' => 0,
                    'sessions' => 0,
                ];
            }
            // Admin exclusion: drop the part of the session before the ban
            // moment (whole session when it ended before the ban).
            $cutoff = $this->cutoffFor($cutoffs, $mddId, $r->player_name_clean);
            $start = ($cutoff && $cutoff->greaterThan($r->started_at)) ? $cutoff : $r->started_at;
            $end = $r->ended_at ?? now();
            // An open session is being watched right now - count it live. A
            // clipped closed session is re-measured from its timestamps
            // (stored seconds cover the full span).
            if ($start->lessThan($end)) {
                $groups[$key]['seconds'] += ($r->ended_at && $start === $r->started_at)
                    ? (int) $r->seconds
                    : $this->span($start, $end);
            }
            $groups[$key]['sessions']++;
            $groups[$key]['name'] = $r->player_name ?: $groups[$key]['name'];
            if ($mddId) {
                $groups[$key]['mdd_id'] = (int) $mddId;
            }
            if ($r->user_id) {
                $groups[$key]['user_id'] = (int) $r->user_id;
            }
        }

        $entries = array_values($groups);
        usort($entries, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);

        if ($limit !== null) {
            $entries = array_slice($entries, 0, $limit);
        }

        $userIds = array_values(array_filter(array_column($entries, 'user_id')));
        $users = $userIds
            ? User::whereIn('id', $userIds)
                ->get(['id', 'name', 'plain_name', 'profile_photo_path', 'country', 'mdd_id'])
                ->keyBy('id')
            : collect();

        $accountNames = $this->accountNames($entries);

        foreach ($entries as &$e) {
            $u = $e['user_id'] ? $users->get($e['user_id']) : null;
            $e['user'] = $u ? [
                'id' => $u->id,
                'profile_photo_path' => $u->profile_photo_path,
                'country' => $u->country,
            ] : null;
            $e['name'] = $accountNames[$e['mdd_id']] ?? $e['name'];
        }

        return $entries;
    }

    /** Tickets drawn per contest ("best of three"). */
    public const DRAW_PICKS = 3;

    /**
     * Draw the contest winner: a watch-time-weighted raffle (1 ticket per full
     * minute watched), best of three. Three distinct ticket numbers are drawn
     * from the pool; of the people holding them, the one with the most watch
     * time wins (a tie goes to the one drawn first). One ticket still has the
     * textbook odds of being drawn, but a big watcher gets three chances of
     * being in the final three and then beats whoever else is there.
     *
     * Persists winner + ticket transparency fields (winning_ticket is the
     * winner's drawn number, draw_picks the three picks in draw order) and
     * marks the contest closed. Returns the winning entry, or null if nobody
     * accrued at least one ticket.
     */
    public function draw(DefragliveContest $contest): ?array
    {
        $entries = array_values(array_filter(
            $this->leaderboard($contest, null),
            fn ($e) => $e['tickets'] >= 1
        ));

        $total = array_sum(array_column($entries, 'tickets'));
        if ($total < 1) {
            return null;
        }

        // Distinct numbers, so a pool of one or two tickets still terminates.
        $numbers = [];
        $want = min(self::DRAW_PICKS, $total);
        while (count($numbers) < $want) {
            $n = random_int(1, $total);
            if (! in_array($n, $numbers, true)) {
                $numbers[] = $n;
            }
        }

        $picks = [];
        foreach ($numbers as $n) {
            $holder = $this->holderOf($entries, $n);
            $picks[] = [
                'ticket' => $n,
                'mdd_id' => $holder['mdd_id'],
                'user_id' => $holder['user_id'],
                'name' => $holder['name'],
                'seconds' => $holder['seconds'],
                'tickets' => $holder['tickets'],
            ];
        }

        // Most watch time wins; on a tie the earlier pick stays (stable sort).
        $ranked = $picks;
        usort($ranked, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);
        $winning = $ranked[0]['ticket'];
        $winner = $this->holderOf($entries, $winning);

        $contest->update([
            'winner_mdd_id' => $winner['mdd_id'],
            'winner_user_id' => $winner['user_id'],
            'winner_name' => $winner['name'],
            'winner_seconds' => $winner['seconds'],
            'winner_tickets' => $winner['tickets'],
            'total_tickets' => $total,
            'winning_ticket' => $winning,
            'draw_picks' => $picks,
            'drawn_at' => now(),
            'status' => DefragliveContest::STATUS_CLOSED,
        ]);

        return $winner;
    }

    /**
     * The entry holding ticket number $n when the entries' tickets are laid
     * end to end in leaderboard order and numbered from 1.
     */
    private function holderOf(array $entries, int $n): array
    {
        $cursor = 0;
        foreach ($entries as $e) {
            $cursor += $e['tickets'];
            if ($n <= $cursor) {
                return $e;
            }
        }

        return $entries[array_key_last($entries)];
    }

    /**
     * Per-identity exclusion cutoffs from admin bans: `key => Carbon`, where
     * watch time BEFORE the cutoff is discarded. Keys mirror keyFor() for both
     * identity forms so an exclusion hits sessions whether or not they resolved
     * to an mdd account. Multiple bans of the same player keep the latest.
     */
    private function exclusionCutoffs(): array
    {
        $cutoffs = [];
        foreach (DefragliveWatchExclusion::all() as $x) {
            $keys = [];
            if ($x->mdd_id) {
                $keys[] = 'mdd:'.$x->mdd_id;
            }
            if ($x->name_clean) {
                $keys[] = 'name:'.$x->name_clean;
            }
            foreach ($keys as $key) {
                if (! isset($cutoffs[$key]) || $x->excluded_before->greaterThan($cutoffs[$key])) {
                    $cutoffs[$key] = $x->excluded_before;
                }
            }
        }

        return $cutoffs;
    }

    /**
     * The exclusion cutoff applying to one session row (by either identity),
     * or null. Rows matched by name keep being excluded even after they later
     * resolve to an mdd account and vice versa.
     */
    private function cutoffFor(array $cutoffs, $mddId, ?string $clean): ?\Carbon\Carbon
    {
        $byMdd = $mddId ? ($cutoffs['mdd:'.(int) $mddId] ?? null) : null;
        $byName = $clean ? ($cutoffs['name:'.$clean] ?? null) : null;
        if ($byMdd && $byName) {
            return $byMdd->greaterThan($byName) ? $byMdd : $byName;
        }

        return $byMdd ?? $byName;
    }

    /** Strip Quake 3 colour codes, collapse whitespace, lowercase: a stable key. */
    public function cleanName(string $name): string
    {
        $plain = preg_replace('/\^[0-9A-Za-z]/', '', $name);
        $plain = trim(preg_replace('/\s+/', ' ', $plain ?? ''));

        return mb_strtolower($plain);
    }

    private function keyFor($mddId, ?string $clean): string
    {
        return $mddId ? 'mdd:'.(int) $mddId : 'name:'.(string) $clean;
    }

    /**
     * Which account each watched nick belongs to.
     *
     * A login the game reported is the only thing that decides, so it is asked
     * first, and a nick it has answered for is never overruled. What is left
     * over - a nick nobody was ever logged in behind - falls to the alias list,
     * under the conditions in mddByEstablishedAlias().
     */
    private function identityByCleanName($rows): array
    {
        $known = $this->mddByCleanName($rows);

        return $known + $this->mddByEstablishedAlias($rows, $known);
    }

    /**
     * Which account each nick belongs to, learned from the sessions themselves.
     *
     * A session is resolved to an mdd_id at the moment it is recorded, from the
     * live player list, and that only works while the player is connected and
     * logged in. The same person watched under the same nick outside that window
     * is stored with no id at all, and then counts as a separate contestant with
     * their own row. Reading the answer back off the sessions that did resolve
     * repairs the rest without asking anything else.
     *
     * Only a nick the game has answered the same way every time is used. Twelve
     * of the nicks watched so far have been reported as two different accounts -
     * two people really do play as "kali" - and there is no way to tell which of
     * them the unresolved session belongs to, so it stays its own contestant.
     * The engine default is skipped outright: nearly two hundred accounts have
     * been seen as UnnamedPlayer, so it identifies nobody.
     */
    private function mddByCleanName($rows): array
    {
        $seen = [];

        foreach ($rows as $r) {
            if ($r->mdd_id && $r->player_name_clean && ! $this->isDefaultName($r->player_name_clean)) {
                $seen[$r->player_name_clean][(int) $r->mdd_id] = true;
            }
        }

        $known = [];

        foreach ($seen as $clean => $accounts) {
            if (count($accounts) === 1) {
                $known[$clean] = (int) array_key_first($accounts);
            }
        }

        return $known;
    }

    /**
     * Accounts for nicks no login ever answered for, from the alias list.
     *
     * An alias on its own is a bad witness - it says an account once used a
     * name, not that whoever is using it now is them, and crediting frog for an
     * evening watched by a suburb who was logged in as nobody is exactly what
     * came of trusting it. Two conditions make it worth listening to anyway:
     * the account has to have played under that name more than a handful of
     * times, so it is a name of theirs rather than one evening's joke (suburb
     * was used once), and no second account may have ever used it, because then
     * there is demonstrably more than one person answering to it.
     *
     * This is deliberately worked out at read time and never written onto a
     * session. What the session stores stays what the game said: a login, or
     * nothing.
     */
    private function mddByEstablishedAlias($rows, array $known): array
    {
        $wanted = [];

        foreach ($rows as $r) {
            $clean = (string) $r->player_name_clean;
            if (! $r->mdd_id && $clean !== '' && ! isset($known[$clean])) {
                $wanted[$clean] = true;
            }
        }

        if (! $wanted) {
            return [];
        }

        return array_intersect_key($this->establishedAliases(), $wanted);
    }

    /**
     * Every nick the alias list is allowed to speak for, as clean nick => account.
     *
     * Cached for an hour: this walks all 23k aliases, the leaderboard is public,
     * and an alias added on a profile is in no hurry.
     */
    private function establishedAliases(): array
    {
        return Cache::remember('defraglive:established-aliases', 3600, function () {
            $claims = [];

            foreach (UserAlias::whereNotNull('mdd_id')->get(['mdd_id', 'alias', 'usage_count']) as $alias) {
                $clean = $this->cleanName((string) $alias->alias);

                if ($clean === '' || $this->isDefaultName($clean)) {
                    continue;
                }

                $mddId = (int) $alias->mdd_id;
                $claims[$clean][$mddId] = max($claims[$clean][$mddId] ?? 0, (int) $alias->usage_count);
            }

            $map = [];

            foreach ($claims as $clean => $accounts) {
                if (count($accounts) !== 1) {
                    continue;
                }

                $mddId = array_key_first($accounts);

                if ($accounts[$mddId] > self::ALIAS_MIN_USES) {
                    $map[$clean] = (int) $mddId;
                }
            }

            return $map;
        });
    }

    /**
     * A name the engine hands out rather than one a player chose, so it says
     * nothing about who is behind it.
     */
    private function isDefaultName(string $clean): bool
    {
        return in_array($clean, ['unnamedplayer', 'player', 'unknownplayer'], true);
    }

    /**
     * The name an account goes by, for every mdd_id in the given entries.
     *
     * Without this the leaderboard shows whichever nick was seen last, so a
     * player who spent one evening under a different name is renamed for good,
     * watch time and all - which is how frog turned into suburb. The account's
     * own name is the stable answer; a player nobody has an account for keeps
     * the nick their sessions carry.
     */
    private function accountNames(array $entries): array
    {
        $mddIds = array_values(array_filter(array_column($entries, 'mdd_id')));

        if (! $mddIds) {
            return [];
        }

        $names = MddProfile::whereIn('id', $mddIds)->pluck('name', 'id')->all();

        // A site account outranks the mdd profile: it is the name the player
        // chose here, and the one the rest of the site shows.
        foreach (User::whereIn('mdd_id', $mddIds)->get(['mdd_id', 'name']) as $user) {
            if ($user->name) {
                $names[(int) $user->mdd_id] = $user->name;
            }
        }

        return $names;
    }

    /** Is the "current player" actually the bot self-spectating (idle)? */
    private function isBot(array $current, array $serverstate): bool
    {
        $botId = $serverstate['bot_id'] ?? null;
        if ($botId !== null && isset($current['id']) && (int) $current['id'] === (int) $botId) {
            return true;
        }

        $name = mb_strtolower((string) ($current['n'] ?? ''));

        return str_contains($name, 'defrag.live') || str_contains($name, 'defraglive');
    }
}
