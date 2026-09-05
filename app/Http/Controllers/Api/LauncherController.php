<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDemoJob;
use App\Models\CompRound;
use App\Models\Map;
use App\Models\Notification;
use App\Models\Record;
use App\Models\RecordNotification;
use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use App\Services\Comps\CompsApiPayload;
use App\Services\Comps\SubmissionIntake;
use App\Services\ServerListService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints consumed by the desktop launcher (tauri app). All routes sit
 * behind a Sanctum personal access token issued at /user/launcher-tokens.
 * Two ability scopes: launcher:upload (write - demo uploads) and
 * launcher:read (server browser + notifications feed). Every request
 * carries `Authorization: Bearer <token>`.
 */
class LauncherController extends Controller
{
    /**
     * Given an MD5 hash of a demo file, tell the launcher whether it already
     * exists on the server. Prevents re-uploading demos that were previously
     * backed up (e.g. after a reinstall) or were uploaded through the web.
     *
     * Expected body:   { "hash": "<32-char md5>" }
     * Response 200:    { "exists": bool, "demo_id": int|null }
     */
    public function lookupByHash(Request $request)
    {
        $data = $request->validate([
            'hash' => 'required|string|size:32',
        ]);

        // withUnreleasedComps() so a comps entry answers "yes, this file is
        // already here". Without it the global scope hides it, the launcher
        // hears `exists: false`, and its next move is to upload the user's own
        // comps run through the ordinary endpoint - which publishes it, with
        // its time, in the middle of the round. Only `exists` and `demo_id`
        // leave this method, so seeing the row gives nothing else away.
        $demo = UploadedDemo::withUnreleasedComps()
            ->where('file_hash', strtolower($data['hash']))
            ->first(['id', 'file_path', 'record_id', 'comps_hidden_until']);

        // A row whose file never arrived is not a backup, and answering "yes"
        // for it is how a demo gets lost for good: the launcher writes the
        // file off as already backed up and never offers it again.
        if ($demo && self::isAbandoned($demo)) {
            $demo = null;
        }

        return response()->json([
            'exists' => $demo !== null,
            'demo_id' => $demo?->id,
        ]);
    }

    /**
     * A row that claims a hash but holds no file.
     *
     * It happens when an upload dies between the INSERT and the file landing
     * on disk - the write below is now one transaction, but rows from before
     * that are still in the table (2087 of them from March 2026), and
     * file_hash is unique, so each one blocks its demo from ever being sent
     * again. Nothing is lost by letting the file back in: there are no bytes
     * behind these rows on any disk, and one that carries a record or a comps
     * entry is left alone regardless.
     */
    private static function isAbandoned(UploadedDemo $demo): bool
    {
        return $demo->file_path === ''
            && $demo->record_id === null
            && ! $demo->isHeldForComps();
    }

    /**
     * The `file_mtime` the launcher sent, as a timestamp, or null.
     *
     * Clamped to now: a clock running ahead would otherwise write a date in the
     * future, and a future date passes every "was this made after the ballot
     * opened" check there is.
     */
    private static function clientMtime(Request $request): ?Carbon
    {
        $seconds = $request->input('file_mtime');

        if (! $seconds) {
            return null;
        }

        $at = Carbon::createFromTimestamp((int) $seconds);

        return $at->isFuture() ? now() : $at;
    }

    /**
     * Single-file demo upload. The launcher calls /lookup-by-hash first, so
     * duplicates are the exception, not the rule - we still defend against
     * them because two launchers on different PCs could race on the same
     * demo.
     *
     * Expected multipart body:
     *   demo     (file, .dm_68/.dm_69 etc.)
     *   hash     (optional md5 the launcher already computed; the stored hash
     *             is always taken from the bytes that arrived, and this one is
     *             only compared against it for the log)
     *
     * Response 200: { "demo_id": int, "status": "uploaded" }
     * Response 409: { "error": "duplicate", "demo_id": int }
     * Response 500: { "error": <sentence> }  - nothing was written, send again
     */
    public function uploadDemo(Request $request)
    {
        $user = $request->user();

        if (! $user->canUploadDemos()) {
            return response()->json([
                'error' => 'Your account has been restricted from uploading demos.',
            ], 403);
        }

        $request->validate([
            'demo' => 'required|file|max:512000', // 512 MB, same cap as the web form
            'hash' => 'nullable|string|size:32',
            // Unix seconds: when the file was last written on the uploader's
            // own disk. An HTTP upload carries no such thing on its own, and
            // comps needs it to tell a run made this week from a demo that has
            // been sitting on a hard drive since 2019.
            'file_mtime' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('demo');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        if (! preg_match('/^dm_\d+$/', $extension)) {
            return response()->json([
                'error' => 'Invalid demo file extension (expected dm_68/dm_69/...).',
            ], 422);
        }

        // The hash is computed from the bytes that arrived, not taken from the
        // launcher's word for them. It is the identity of the file everywhere
        // on the site - the duplicate check, the render request, the match to a
        // record - and a stored hash that is not the hash of the stored file is
        // a row that lies quietly for as long as nobody hashes it again. The
        // launcher's claim is still read, only to say so in the log when the
        // two disagree.
        $claimed = strtolower((string) $request->input('hash'));
        $hash = strtolower(md5_file($file->getPathname()));

        if ($claimed !== '' && $claimed !== $hash) {
            Log::warning('Launcher upload hash mismatch', [
                'user_id' => $user->id,
                'claimed' => $claimed,
                'actual' => $hash,
                'filename' => $originalName,
            ]);
        }

        // Short-circuit: if the hash is already in the DB, treat as duplicate.
        // Returning 409 so the launcher can skip + mark local demo as "already backed up".
        //
        // withUnreleasedComps() for the same reason as lookupByHash: file_hash
        // is unique, so a comps entry the scope hid from us would fail on the
        // insert below anyway - as a 500 rather than a clean 409, and only
        // after we had already decided to publish the user's own comps run.
        $existing = UploadedDemo::withUnreleasedComps()
            ->where('file_hash', $hash)
            ->first();

        // An abandoned row is taken over rather than replaced: file_hash is
        // unique, so a second row for the same demo is impossible, and the id
        // is what anything already pointing at it would be pointing at.
        $abandoned = $existing !== null && self::isAbandoned($existing);

        if ($existing && ! $abandoned) {
            return response()->json([
                'error' => 'duplicate',
                'demo_id' => $existing->id,
                'status' => $existing->status,
            ], 409);
        }

        // The row and the file land together or not at all. Written the other
        // way round - row first, file after - a failure in between (a full
        // disk, a killed worker) left a hash in the table with nothing behind
        // it, and because that hash then answered "already backed up", the
        // demo was never offered again.
        try {
            $demo = DB::transaction(function () use ($existing, $abandoned, $file, $originalName, $hash, $user, $request) {
                $fields = [
                    'original_filename' => $originalName,
                    'file_path' => '',
                    'file_size' => $file->getSize(),
                    'file_hash' => $hash,
                    'user_id' => $user->id,
                    'status' => 'uploaded',
                    'source' => UploadedDemo::SOURCE_LAUNCHER,
                    'client_file_mtime' => self::clientMtime($request),
                ];

                if ($abandoned) {
                    // Whatever the old attempt failed at is not this file's
                    // history, so the note explaining it goes too.
                    $existing->update($fields + ['processing_output' => null, 'validity' => null]);
                    $demo = $existing;
                } else {
                    $demo = UploadedDemo::create($fields);
                }

                $directory = storage_path("app/demos/temp/{$demo->id}");
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                $file->move($directory, $originalName);
                $demo->update(['file_path' => "demos/temp/{$demo->id}/{$originalName}"]);

                return $demo;
            });
        } catch (\Throwable $e) {
            Log::error('Launcher demo upload failed', [
                'user_id' => $user->id,
                'hash' => $hash,
                'filename' => $originalName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'The upload could not be stored. Nothing was saved, so the launcher can send it again.',
            ], 500);
        }

        // After the commit, never inside it: a worker fast enough to pick the
        // job up mid-transaction would look for a row that is not there yet.
        ProcessDemoJob::dispatch($demo);

        Log::info('Launcher demo upload', [
            'user_id' => $user->id,
            'demo_id' => $demo->id,
            'hash' => $hash,
            'size' => $demo->file_size,
        ]);

        return response()->json([
            'demo_id' => $demo->id,
            'status' => 'uploaded',
        ]);
    }

    /**
     * What comps is doing, for the launcher's Comps tab.
     *
     * The launcher needs the map being played in each physics for more than
     * display: it is how it recognises a run on this week's map and keeps that
     * demo out of the ordinary, immediately-public upload path. So this is
     * cached briefly rather than not at all - a thousand launchers polling every
     * five minutes should not be a thousand queries - but not longer, because a
     * stale map is a demo published by mistake.
     *
     * Response 200: { playing: {...}|null, voting: {...}|null }
     */
    public function comps(Request $request, CompsApiPayload $payload)
    {
        $user = $request->user();

        // The round itself is the same for everybody; only the caller's own
        // entries differ, so the shared half is what gets cached.
        $shared = Cache::remember('comps:launcher_payload', 60, fn () => $payload->build(null));

        if ($user) {
            $mine = $payload->build($user);

            if ($shared['playing']) {
                $shared['playing']['my_entries'] = $mine['playing']['my_entries'] ?? [];
            }

            $shared['my_notices'] = $mine['my_notices'];
            $shared['entry_gate'] = $mine['entry_gate'];
        }

        return response()->json($shared);
    }

    /**
     * Enter a demo into the round being played.
     *
     * Same rules as the upload form on the comps page, because both go through
     * SubmissionIntake - a rule enforced on only one of the two routes would
     * not surface as an error, it would surface as a run that counted one way
     * and not the other.
     *
     * `auto` marks an entry the launcher decided on by reading the demo's
     * filename. That is a convention, not a promise, so the server checks again
     * once the file is parsed and an auto entry that turns out not to be a run
     * of this map is withdrawn silently, leaving an ordinary upload behind.
     * See SubmissionValidator::reject.
     *
     * Response 200: { demo_id, submission_id, status: "pending" }
     * Response 409: { error: "duplicate" }  - already uploaded
     * Response 422: { error: <sentence> }   - round closed, wrong file type
     */
    public function compsUpload(Request $request, SubmissionIntake $intake)
    {
        $user = $request->user();

        if (! $user->canUploadDemos()) {
            return response()->json([
                'error' => 'Your account has been restricted from uploading demos.',
            ], 403);
        }

        // Entering needs a linked Q3DF.org profile, the same as on the site. A
        // rule enforced on one route only is not a rule, it is a detour.
        if ($reason = $intake->userRejectionReason($user)) {
            return response()->json(['error' => $reason], 422);
        }

        $data = $request->validate([
            'demo' => ['required', 'file', 'max:' . SubmissionIntake::MAX_KB],
            'round_id' => ['required', 'integer'],
            'auto' => ['sometimes', 'boolean'],
            'file_mtime' => ['nullable', 'integer', 'min:0'],
        ]);

        $round = CompRound::find($data['round_id']);

        if (! $round) {
            return response()->json(['error' => 'No such round.'], 404);
        }

        $file = $request->file('demo');
        $hash = $intake->hash($file);

        if ($reason = $intake->rejectionReason($round, $file, $hash)) {
            // A duplicate is the one case the launcher can act on by itself -
            // it means the file is already on the server, so the local copy is
            // backed up and needs no retry. Everything else is for the user.
            $duplicate = UploadedDemo::withUnreleasedComps()
                ->where('file_hash', $hash)
                ->exists();

            return response()->json(
                $duplicate ? ['error' => 'duplicate'] : ['error' => $reason],
                $duplicate ? 409 : 422
            );
        }

        $submission = $intake->accept(
            $round,
            $user,
            $file,
            $hash,
            isHighlight: false,
            autoEntered: (bool) ($data['auto'] ?? false),
            clientMtime: self::clientMtime($request),
        );

        return response()->json([
            'demo_id' => $submission->uploaded_demo_id,
            'submission_id' => $submission->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Server browser feed. Same payload the web /servers page consumes via
     * /api/servers/live - per-user mytime / myrank fields are populated
     * for the token's owner, so the launcher can show "your PB on this
     * map" the same way the website does. mapdata.thumbnail is already
     * included by the shared service.
     *
     * Response 200: [ { id, name, ip, port, map, mapdata: {thumbnail, ...},
     *                   onlinePlayers: [...], mytime_time, myrank_position,
     *                   besttime_*, ... } ]
     */
    public function servers(Request $request, ServerListService $servers)
    {
        return response()->json([
            'servers' => $servers->list($request),
        ]);
    }

    /**
     * Notifications feed: record-related (PB beaten, WR taken) plus
     * system (alias suggestions, demome events, etc.). Mirrors the web
     * notification center, filtered to the authenticated user via the
     * `user_id` column on both tables. Page size and order match the
     * web's NotificationsController so the launcher can poll without
     * surprising the user with a different ordering.
     */
    public function notifications(Request $request)
    {
        $userId = $request->user()->id;

        $records = RecordNotification::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get();

        $system = Notification::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get();

        $unreadRecords = RecordNotification::where('user_id', $userId)
            ->where('read', false)
            ->count();

        $unreadSystem = Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();

        return response()->json([
            'records' => $records,
            'system' => $system,
            'unread' => [
                'records' => $unreadRecords,
                'system' => $unreadSystem,
                'total' => $unreadRecords + $unreadSystem,
            ],
        ]);
    }

    /**
     * Per-row toggle for a record notification. Flips `read` and
     * returns the new state + fresh unread counts so the launcher
     * can update its badge without a separate /notifications poll.
     * 404 when the row doesn't exist OR belongs to a different user
     * (the where on user_id is what enforces tenant isolation).
     */
    public function notificationRecordToggle(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $row = RecordNotification::where('user_id', $userId)->where('id', $id)->first();
        if (! $row) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $row->read = ! $row->read;
        $row->save();
        return response()->json([
            'id' => $row->id,
            'read' => (bool) $row->read,
            'unread' => $this->unreadCounts($userId),
        ]);
    }

    /** Mark every record notification for this user as read. */
    public function notificationRecordsMarkRead(Request $request)
    {
        $userId = $request->user()->id;
        RecordNotification::where('user_id', $userId)->where('read', false)->update(['read' => true]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    /** Mark every record notification for this user as unread. */
    public function notificationRecordsMarkUnread(Request $request)
    {
        $userId = $request->user()->id;
        RecordNotification::where('user_id', $userId)->where('read', true)->update(['read' => false]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    /** System notification equivalents. Same shape as the record ones. */
    public function notificationSystemToggle(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $row = Notification::where('user_id', $userId)->where('id', $id)->first();
        if (! $row) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $row->read = ! $row->read;
        $row->save();
        return response()->json([
            'id' => $row->id,
            'read' => (bool) $row->read,
            'unread' => $this->unreadCounts($userId),
        ]);
    }

    public function notificationSystemMarkRead(Request $request)
    {
        $userId = $request->user()->id;
        Notification::where('user_id', $userId)->where('read', false)->update(['read' => true]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    public function notificationSystemMarkUnread(Request $request)
    {
        $userId = $request->user()->id;
        Notification::where('user_id', $userId)->where('read', true)->update(['read' => false]);
        return response()->json(['unread' => $this->unreadCounts($userId)]);
    }

    /**
     * Shared unread-count payload returned by every mark-read/unread
     * mutation above. Keeps the launcher bell badge in sync with what
     * the server just changed, without a separate /notifications
     * round-trip.
     */
    private function unreadCounts(int $userId): array
    {
        $records = RecordNotification::where('user_id', $userId)->where('read', false)->count();
        $system = Notification::where('user_id', $userId)->where('read', false)->count();
        return [
            'records' => $records,
            'system' => $system,
            'total' => $records + $system,
        ];
    }

    /**
     * Lightweight bell-badge poll target. Returns ONLY the unread
     * counts (~30B JSON) so the launcher's background poll doesn't
     * fetch 50 records + 50 system notifications every 3 minutes.
     * The full /notifications endpoint is reserved for the Notifications
     * view (mount + manual refresh).
     */
    public function notificationsUnreadCount(Request $request)
    {
        return response()->json($this->unreadCounts($request->user()->id));
    }

    /**
     * Minimal "who am I" for the launcher. Returns the fields the
     * launcher needs to wire up the top nav's Profile button (mdd_id
     * for the /profile/{id} link, name + country for the badge) and
     * nothing else. Used once per app start; cached in the launcher's
     * config store so the button works offline thereafter.
     *
     * Lives under the launcher-read bucket so a misclick on the
     * Profile button can't be turned into a 429 by the global
     * throttle:api ceiling.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'mdd_id' => $user->mdd_id,
            'name' => $user->name,
            'plain_name' => $user->plain_name ?? null,
            'country' => $user->country ?? null,
        ]);
    }

    /**
     * Paginated recent records for the launcher's Records tab. Single
     * physics per call (the launcher renders two tables side-by-side
     * and queries each independently) so the response stays small and
     * the per-page count is honest.
     *
     * Deliberately a minimal projection of what RecordsController
     * returns to the web Inertia page - no PlayerMapScore enrichment,
     * no rating multipliers, no offline_records merge. The launcher
     * lists "newest records, by physics" as a quick browser; users
     * who want the rich rating context click through to the web map
     * page anyway.
     *
     * Query: ?physics=vq3|cpm  (defaults to vq3)
     *        &page=1
     */
    public function records(Request $request)
    {
        $data = $request->validate([
            'physics' => 'nullable|in:vq3,cpm',
            'page' => 'nullable|integer|min:1|max:1000',
        ]);

        $physics = $data['physics'] ?? 'vq3';
        $page = $data['page'] ?? 1;
        $perPage = 50;

        $records = Record::query()
            ->where('physics', $physics)
            ->with(['user:id,name,plain_name,country,profile_photo_path'])
            ->orderBy('date_set', 'DESC')
            ->orderBy('id', 'DESC')
            ->simplePaginate($perPage, ['id', 'name', 'country', 'mdd_id', 'mapname', 'rank', 'time', 'date_set', 'physics', 'mode'], 'page', $page);

        return response()->json($records);
    }

    /**
     * Paginated map list for the launcher's Maps tab. Newest first,
     * optional name search. The launcher intentionally doesn't expose
     * the web's MapFilters surface - if the user wants to filter by
     * weapon / gametype / NSFW / etc. they click through to the
     * matching map page and get the web's full filter UI.
     *
     * Query: ?page=1  &search=optional-substring
     */
    public function maps(Request $request)
    {
        $data = $request->validate([
            'page' => 'nullable|integer|min:1|max:1000',
            'search' => 'nullable|string|max:64',
        ]);

        $page = $data['page'] ?? 1;
        $search = $data['search'] ?? null;
        $perPage = 50;

        $query = Map::query()
            ->select('id', 'name', 'author', 'thumbnail', 'physics', 'gametype', 'is_nsfw', 'date_added', 'pk3', 'weapons', 'items', 'functions')
            ->orderBy('date_added', 'DESC')
            ->orderBy('id', 'DESC');

        if ($search !== null && $search !== '') {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        return response()->json(
            $query->paginate($perPage, ['*'], 'page', $page)
        );
    }

    /**
     * Request a YouTube render for a demo the launcher already
     * uploaded. Mirrors the web RenderRequestController flow, with two
     * key differences:
     *
     *  - lookup by file_hash OR demo_id (launcher has the hash locally
     *    from uploaded.json without needing the demo_id round-trip)
     *  - record_id is optional (launcher renders are demo-driven, not
     *    record-driven; demome's pipeline handles record_id=null)
     *
     * If the demo already has a non-failed RenderedVideo we short-
     * circuit and return its current status / youtube_url so the
     * launcher can show "already rendered" without a second queue
     * entry. Same 20-renders-per-day quota as the web button, shared
     * cache key so the user can't exceed it by bouncing between web
     * and launcher.
     *
     * Notification on completion comes for free - DemomeController's
     * markPublished() already fires a `render_completed` Notification
     * when the YouTube upload succeeds, regardless of source.
     */
    public function renderVideo(Request $request)
    {
        $data = $request->validate([
            'demo_id' => 'nullable|integer|exists:uploaded_demos,id',
            'file_hash' => 'nullable|string|size:32',
        ]);

        if (empty($data['demo_id']) && empty($data['file_hash'])) {
            return response()->json([
                'error' => 'Pass either demo_id or file_hash.',
            ], 422);
        }

        $user = $request->user();

        if (! $user->canUploadDemos()) {
            return response()->json([
                'error' => 'Your account is restricted from rendering.',
            ], 403);
        }

        $demo = isset($data['demo_id'])
            ? UploadedDemo::find($data['demo_id'])
            : UploadedDemo::where('file_hash', strtolower($data['file_hash']))->first();

        if (! $demo) {
            // A demo comps is holding is hidden from this lookup by the global
            // scope, and answering "upload it first" would send the launcher
            // round the loop again. Say what is actually happening instead, and
            // do not queue: a render publishes the run on YouTube, which is the
            // one place a held run must not appear.
            $held = UploadedDemo::withUnreleasedComps()
                ->when(isset($data['demo_id']), fn ($q) => $q->whereKey($data['demo_id']))
                ->when(! isset($data['demo_id']), fn ($q) => $q->where('file_hash', strtolower($data['file_hash'])))
                ->first(['id', 'comps_hidden_until']);

            if ($held) {
                return response()->json([
                    'error' => 'This run is on a map comps is using. It can be rendered once the round is over.',
                ], 409);
            }

            return response()->json([
                'error' => 'Demo not found. Upload it first via /upload-demo.',
                'needs_upload' => true,
            ], 404);
        }

        // Already in the pipeline (any non-failed state) - hand back
        // whatever we have so the launcher can show progress instead
        // of double-queueing.
        $existing = RenderedVideo::where('demo_id', $demo->id)
            ->whereIn('status', ['pending', 'rendering', 'uploading', 'completed'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return response()->json([
                'already_queued' => true,
                'id' => $existing->id,
                'status' => $existing->status,
                'youtube_url' => $existing->youtube_url,
                'youtube_video_id' => $existing->youtube_video_id,
            ]);
        }

        // Same daily quota as the web request flow - shared cache key
        // so a user can't sidestep the cap by alternating between
        // /render-request on the web and /render-video here.
        $cacheKey = "render_requests_user_{$user->id}_" . now()->format('Y-m-d');
        $todayCount = Cache::get($cacheKey, 0);
        if ($todayCount >= 20) {
            return response()->json([
                'error' => 'Daily render limit reached (20/day).',
                'remaining' => 0,
            ], 429);
        }

        // A map that plays itself is worth no render. Same rule the automatic
        // queue uses, so a person cannot ask for what the queue would refuse.
        if (\App\Services\JokeMaps::isJoke($demo->map_name, $demo->physics)) {
            return response()->json([
                'error' => 'This map is not eligible for rendering: too many players share its record time.',
            ], 422);
        }

        $demoUrl = config('app.url') . "/api/demome/download-demo/{$demo->id}";

        $video = RenderedVideo::create([
            'map_name' => $demo->map_name,
            'player_name' => $demo->player_name,
            'physics' => $demo->physics,
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'demo_id' => $demo->id,
            // Inherit the demo's online record link so the render surfaces
            // under that MDD record on the map (same as the web render flow).
            // Null for offline / unmatched demos - those surface via the
            // demo's own renderedVideo relation in Demos Top instead.
            'record_id' => $demo->record_id,
            'user_id' => $user->id,
            'source' => 'launcher',
            'requested_by' => $user->name,
            'status' => 'pending',
            'priority' => 0,
            'demo_url' => $demoUrl,
            'demo_filename' => $demo->original_filename,
        ]);

        Cache::put($cacheKey, $todayCount + 1, now()->endOfDay());

        $queuePosition = RenderedVideo::where('status', 'pending')
            ->where('id', '<', $video->id)
            ->count() + 1;

        return response()->json([
            'success' => true,
            'id' => $video->id,
            'status' => 'pending',
            'queue_position' => $queuePosition,
            'remaining_today' => 20 - ($todayCount + 1),
        ]);
    }

    /**
     * Fast status check for a render the launcher previously queued.
     * Used by the Library view to refresh the YouTube link without
     * the user having to wait for the next notifications poll.
     * Cheaper than fetching all notifications.
     */
    public function renderStatus(Request $request)
    {
        $data = $request->validate([
            'demo_id' => 'nullable|integer|exists:uploaded_demos,id',
            'file_hash' => 'nullable|string|size:32',
        ]);

        if (empty($data['demo_id']) && empty($data['file_hash'])) {
            return response()->json([
                'error' => 'Pass either demo_id or file_hash.',
            ], 422);
        }

        $demo = isset($data['demo_id'])
            ? UploadedDemo::find($data['demo_id'])
            : UploadedDemo::where('file_hash', strtolower($data['file_hash']))->first();

        if (! $demo) {
            return response()->json([
                'has_render' => false,
                'reason' => 'demo_not_uploaded',
            ]);
        }

        $video = RenderedVideo::where('demo_id', $demo->id)
            ->orderByDesc('id')
            ->first();

        if (! $video) {
            return response()->json([
                'has_render' => false,
                'demo_id' => $demo->id,
            ]);
        }

        return response()->json([
            'has_render' => true,
            'demo_id' => $demo->id,
            'id' => $video->id,
            'status' => $video->status,
            'youtube_url' => $video->youtube_url,
            'youtube_video_id' => $video->youtube_video_id,
        ]);
    }

    /**
     * Bulk reconcile of completed YouTube renders for the launcher.
     *
     * Returns a compact { file_hash => youtube_video_id } map of every
     * completed, visible render (the launcher rebuilds the watch URL from the
     * id, so we ship the minimal 11-char string, not the full URL), letting
     * the Demos list show "watch on YouTube" without a per-demo round-trip.
     *
     * Pass ?since=<unix ts> for a delta: only renders whose row changed after
     * that time come back, plus a `removed` list of hashes whose render is no
     * longer completed/visible (unpublished/hidden), so a long-open launcher
     * stays in sync cheaply. `synced_at` is the cursor to pass as `since` next
     * time (rewound 2s so a same-second update can't slip through the crack;
     * the launcher merge is idempotent so the re-overlap is harmless).
     */
    public function renderedIndex(Request $request)
    {
        $sinceTs = (int) $request->query('since', 0);
        $since = $sinceTs > 0 ? \Illuminate\Support\Carbon::createFromTimestamp($sinceTs) : null;

        $base = RenderedVideo::query()
            ->join('uploaded_demos', 'uploaded_demos.id', '=', 'rendered_videos.demo_id')
            ->whereNotNull('uploaded_demos.file_hash')
            ->when($since, fn ($q) => $q->where('rendered_videos.updated_at', '>', $since));

        $map = (clone $base)
            ->where('rendered_videos.status', 'completed')
            ->where('rendered_videos.is_visible', true)
            ->whereNotNull('rendered_videos.youtube_video_id')
            ->orderBy('rendered_videos.id') // newest render per hash wins on dedupe
            ->pluck('rendered_videos.youtube_video_id', 'uploaded_demos.file_hash');

        // Removals only matter for a delta - a full sync (no `since`) starts
        // from an empty launcher cache, so there's nothing to remove.
        $removed = [];
        if ($since) {
            $removed = (clone $base)
                ->where(function ($q) {
                    $q->where('rendered_videos.status', '!=', 'completed')
                      ->orWhere('rendered_videos.is_visible', false)
                      ->orWhereNull('rendered_videos.youtube_video_id');
                })
                ->pluck('uploaded_demos.file_hash')
                ->unique()
                ->values();
        }

        return response()->json([
            'map' => $map,
            'removed' => $removed,
            'synced_at' => now()->subSeconds(2)->timestamp,
        ]);
    }
}
