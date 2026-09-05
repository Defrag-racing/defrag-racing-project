<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RenderedVideo;
use App\Models\Record;
use App\Models\UploadedDemo;
use App\Models\SiteSetting;
use App\Services\DemoProcessorService;
use App\Services\JokeMaps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DemomeController extends Controller
{
    public function queue()
    {
        $paused = SiteSetting::getBool('demome:paused', false);

        // Map a RenderedVideo row to the queue payload shape - shared
        // between the normal `items` list and the dedicated `force_render`
        // list so they both ship filtered video_title/description/tags.
        $mapItem = function ($item) {
            return [
                'id' => $item->id,
                'demo_url' => $item->demo_url,
                'demo_filename' => $item->demo_filename,
                'map_name' => $item->map_name,
                'player_name' => $item->player_name,
                'physics' => $item->physics,
                'time_ms' => $item->time_ms,
                'priority' => $item->priority,
                'source' => $item->source,
                'record_id' => $item->record_id,
                'map_page_url' => 'https://defrag.racing/maps/' . $item->map_name,
                'video_title' => \App\Services\VideoMetadataService::generateTitle($item),
                'video_description' => \App\Services\VideoMetadataService::generateDescription($item),
                'video_tags' => \App\Services\VideoMetadataService::generateTags($item),
            ];
        };

        // A demo comps is holding must never reach the render queue. Rendering
        // publishes the run on YouTube, which is the same route into the open
        // the hold exists to close - and it is worse than the site listing it,
        // because a video outlives the round. Written as a NOT EXISTS rather
        // than through the relation on purpose: `uploaded_demos` hides held
        // rows by global scope, so a `whereHas` here would quietly mean the
        // opposite of what it reads like.
        $notHeldForComps = fn ($query) => $query->whereNotExists(fn ($sub) => $sub
            ->selectRaw('1')
            ->from('uploaded_demos')
            ->whereColumn('uploaded_demos.id', 'rendered_videos.demo_id')
            ->whereNotNull('uploaded_demos.comps_hidden_until')
            ->where('uploaded_demos.comps_hidden_until', '>', now()));

        // Force-render queue: always served, ignores `paused`. Items land
        // here when admin clicks the Filament "Force render" action, which
        // sets priority=-1. Bot processes this list unconditionally.
        $forceRender = RenderedVideo::where('status', 'pending')
            ->where('priority', -1)
            ->tap($notHeldForComps)
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get()
            ->map($mapItem);

        // Normal queue: when paused, falls back to the old behavior of
        // serving ONLY priority=-1 items via items[] too. This is
        // intentional redundancy with force_render[] above - an old
        // bot binary that doesn't know about force_render[] still sees
        // force-render rows here and processes them. Newer bots dedupe
        // by id so the row isn't rendered twice.
        $items = ($paused
            ? RenderedVideo::where('status', 'pending')->where('priority', -1)
            : RenderedVideo::where('status', 'pending'))
            ->tap($notHeldForComps)
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get()
            ->map($mapItem);

        // Find stale items stuck in 'rendering' status (crashed uploads)
        // Exclude items that already have a youtube_url (were completed but status got stuck)
        $staleRendering = RenderedVideo::where('status', 'rendering')
            ->whereNull('youtube_url')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'demo_url' => $item->demo_url,
                    'demo_filename' => $item->demo_filename,
                    'map_name' => $item->map_name,
                    'player_name' => $item->player_name,
                    'physics' => $item->physics,
                    'time_ms' => $item->time_ms,
                    'source' => $item->source,
                    'map_page_url' => 'https://defrag.racing/maps/' . $item->map_name,
                ];
            });

        // Items that were rendered but upload failed - just need re-upload
        $uploadPending = RenderedVideo::where('status', 'upload_pending')
            ->orderBy('updated_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'demo_filename' => $item->demo_filename,
                    'map_name' => $item->map_name,
                    'player_name' => $item->player_name,
                    'physics' => $item->physics,
                    'time_ms' => $item->time_ms,
                    'source' => $item->source,
                    'record_id' => $item->record_id,
                    'map_page_url' => 'https://defrag.racing/maps/' . $item->map_name,
                    'video_title' => \App\Services\VideoMetadataService::generateTitle($item),
                    'video_description' => \App\Services\VideoMetadataService::generateDescription($item),
                    'video_tags' => \App\Services\VideoMetadataService::generateTags($item),
                ];
            });

        return response()->json([
            'paused' => $paused,
            'items' => $items,
            'force_render' => $forceRender,
            'stale_rendering' => $staleRendering,
            'upload_pending' => $uploadPending,
        ]);
    }

    public function claim(RenderedVideo $renderedVideo)
    {
        if ($renderedVideo->status !== 'pending') {
            return response()->json(['error' => 'Item already claimed or processed'], 409);
        }

        $updated = DB::table('rendered_videos')
            ->where('id', $renderedVideo->id)
            ->where('status', 'pending')
            ->update(['status' => 'rendering', 'updated_at' => now()]);

        if (!$updated) {
            return response()->json(['error' => 'Item already claimed'], 409);
        }

        Cache::put('demome:current_status', 'rendering', now()->addMinutes(30));
        Cache::put('demome:current_video_id', $renderedVideo->id, now()->addMinutes(30));

        return response()->json(['success' => true]);
    }

    public function complete(RenderedVideo $renderedVideo, Request $request)
    {
        $validated = $request->validate([
            'youtube_url' => 'required|string',
            'youtube_video_id' => 'required|string|max:20',
            'render_duration_seconds' => 'nullable|integer',
            'video_file_size' => 'nullable|integer',
        ]);

        $renderedVideo->update([
            'status' => 'completed',
            // Whatever it failed with last time is no longer true, and a row
            // reading "completed" while still carrying "no space left on
            // device" sends whoever opens it looking for a problem that was
            // fixed by the upload it is showing.
            'failure_reason' => null,
            'youtube_url' => $validated['youtube_url'],
            'youtube_video_id' => $validated['youtube_video_id'],
            'render_duration_seconds' => $validated['render_duration_seconds'] ?? null,
            'video_file_size' => $validated['video_file_size'] ?? null,
            // Non-auto sources are uploaded as public, mark as published immediately
            'published_at' => $renderedVideo->source !== 'auto' ? now() : null,
            'publish_approved' => $renderedVideo->source !== 'auto',
        ]);

        Cache::put('demome:current_status', 'idle', now()->addMinutes(30));
        Cache::forget('demome:current_video_id');

        $this->notifyRenderResult($renderedVideo, true);

        return response()->json(['success' => true]);
    }

    public function fail(RenderedVideo $renderedVideo, Request $request)
    {
        $validated = $request->validate([
            'failure_reason' => 'required|string',
        ]);

        $renderedVideo->update([
            'failure_reason' => $validated['failure_reason'],
            'retry_count' => $renderedVideo->retry_count + 1,
            'status' => 'failed',
        ]);

        Cache::put('demome:current_status', 'idle', now()->addMinutes(30));
        Cache::forget('demome:current_video_id');

        return response()->json(['success' => true, 'will_retry' => $renderedVideo->retry_count < 3]);
    }

    /**
     * Drop a "render is ready" notification into the user's inbox after
     * a render completes. No-op for auto-rendered rows (nobody asked
     * for them, no expectation of feedback) or rows with no linked
     * user_id (Discord renders by non-registered authors). Failures
     * intentionally do NOT notify - the user can't act on a failure,
     * admin will retry from Filament.
     */
    protected function notifyRenderResult(RenderedVideo $renderedVideo, bool $success): void
    {
        if (!$success || !$renderedVideo->user_id || $renderedVideo->source === 'auto') {
            return;
        }

        $mapName = $renderedVideo->map_name ?: 'unknown map';
        $cleanMap = \App\Services\ContentFilter::filterText($mapName);
        $mapUrl = $renderedVideo->map_name
            ? route('maps.map', ['mapname' => $renderedVideo->map_name])
            : null;

        \App\Models\Notification::create([
            'user_id'     => $renderedVideo->user_id,
            'type'        => 'render_completed',
            'before'      => $cleanMap,
            'headline'    => 'render is ready',
            'after'       => '', // column is NOT NULL in DB
            'subheadline' => $mapUrl, // map page link, rendered as a separate clickable on the map name
            'url'         => $renderedVideo->youtube_url,
        ]);
    }

    public function resetToPending(RenderedVideo $renderedVideo)
    {
        if ($renderedVideo->status !== 'rendering') {
            return response()->json(['error' => 'Can only reset items in rendering status'], 409);
        }

        // If already has youtube_url, it was completed - mark as completed, don't reset
        if ($renderedVideo->youtube_url) {
            $renderedVideo->update(['status' => 'completed']);
            return response()->json(['success' => true, 'note' => 'Already had youtube_url, marked as completed']);
        }

        $renderedVideo->update([
            'status' => 'pending',
        ]);

        Cache::put('demome:current_status', 'idle', now()->addMinutes(30));
        Cache::forget('demome:current_video_id');

        return response()->json(['success' => true]);
    }

    public function heartbeat(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:idle,rendering,uploading',
            'current_video_id' => 'nullable|integer',
        ]);

        Cache::put('demome:last_heartbeat', now()->toISOString(), now()->addMinutes(10));
        Cache::put('demome:current_status', $validated['status'], now()->addMinutes(10));

        if ($validated['current_video_id']) {
            Cache::put('demome:current_video_id', $validated['current_video_id'], now()->addMinutes(30));
        } else {
            Cache::forget('demome:current_video_id');
        }

        return response()->json([
            'success' => true,
            'paused' => SiteSetting::getBool('demome:paused', false),
        ]);
    }

    public function report(Request $request)
    {
        $validated = $request->validate([
            'map_name' => 'required|string',
            'player_name' => 'required|string',
            'physics' => 'nullable|string',
            'time_ms' => 'nullable|integer',
            'gametype' => 'nullable|string',
            'demo_url' => 'required|string',
            'demo_filename' => 'nullable|string',
            'youtube_url' => 'required|string',
            'youtube_video_id' => 'required|string|max:20',
            'render_duration_seconds' => 'nullable|integer',
            'video_file_size' => 'nullable|integer',
            'source' => 'required|string|in:discord,web,auto,migration',
            'requested_by' => 'nullable|string',
            'published_at' => 'nullable|date',
            'publish_approved' => 'nullable|boolean',
            'record_id' => 'nullable|integer',
            'demo_id' => 'nullable|integer',
        ]);

        // Check for duplicate by youtube_video_id
        $existing = RenderedVideo::where('youtube_video_id', $validated['youtube_video_id'])->first();
        if ($existing) {
            return response()->json(['success' => true, 'id' => $existing->id, 'duplicate' => true]);
        }

        // Try to match to an existing record
        $recordId = $validated['record_id'] ?? null;
        if (!$recordId && $validated['map_name'] && ($validated['physics'] ?? null) && ($validated['time_ms'] ?? null)) {
            $record = Record::where('mapname', $validated['map_name'])
                ->where('physics', $validated['physics'])
                ->where('time', $validated['time_ms'])
                ->first();

            if ($record) {
                $recordId = $record->id;
            }
        }

        $video = RenderedVideo::create([
            'map_name' => $validated['map_name'],
            'player_name' => $validated['player_name'],
            'physics' => $validated['physics'] ?? null,
            'time_ms' => $validated['time_ms'] ?? null,
            'gametype' => $validated['gametype'] ?? null,
            'record_id' => $recordId,
            'demo_id' => $validated['demo_id'] ?? null,
            'source' => $validated['source'],
            'requested_by' => $validated['requested_by'] ?? null,
            'status' => 'completed',
            'priority' => 3,
            'demo_url' => $validated['demo_url'] ?? null,
            'demo_filename' => $validated['demo_filename'] ?? null,
            'youtube_url' => $validated['youtube_url'],
            'youtube_video_id' => $validated['youtube_video_id'],
            'render_duration_seconds' => $validated['render_duration_seconds'] ?? null,
            'video_file_size' => $validated['video_file_size'] ?? null,
            'is_visible' => true,
            'published_at' => $validated['published_at'] ?? now(),
            'publish_approved' => $validated['publish_approved'] ?? true,
        ]);

        return response()->json(['success' => true, 'id' => $video->id]);
    }

    /**
     * Link a YouTube video to an uploaded demo by MD5 hash.
     * Bot sends only: md5_hash, youtube_url, render_duration_seconds, video_file_size, requested_by
     * Web finds the demo by hash and creates RenderedVideo with metadata from the processed demo.
     */
    public function reportByHash(Request $request)
    {
        $validated = $request->validate([
            'md5_hash' => 'required|string|size:32',
            'youtube_url' => 'required|string',
            'render_duration_seconds' => 'nullable|integer',
            'video_file_size' => 'nullable|integer',
            'requested_by' => 'nullable|string',
        ]);

        // Extract youtube_video_id from URL
        $youtubeVideoId = null;
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $validated['youtube_url'], $matches)) {
            $youtubeVideoId = $matches[1];
        }

        if (!$youtubeVideoId) {
            return response()->json(['success' => false, 'error' => 'Invalid YouTube URL'], 422);
        }

        // Check for duplicate by youtube_video_id
        $existing = RenderedVideo::where('youtube_video_id', $youtubeVideoId)->first();
        if ($existing) {
            return response()->json(['success' => true, 'id' => $existing->id, 'duplicate' => true]);
        }

        // Find the uploaded demo by MD5 hash
        $demo = UploadedDemo::where('file_hash', $validated['md5_hash'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$demo) {
            Log::warning('reportByHash: no demo found for hash', ['md5_hash' => $validated['md5_hash']]);
            return response()->json(['success' => false, 'error' => 'No demo found for this hash'], 404);
        }

        // Use metadata from the processed demo
        $recordId = $demo->record_id;

        // Prefer updating an existing Discord placeholder created by start-discord-render
        // (status=rendering or uploading) linked to this demo, to preserve timeline + avoid duplicates.
        $video = RenderedVideo::where('demo_id', $demo->id)
            ->where('source', 'discord')
            ->whereIn('status', ['rendering', 'uploading'])
            ->whereNull('youtube_video_id')
            ->orderBy('id', 'desc')
            ->first();

        if ($video) {
            $video->update([
                'status' => 'completed',
                'map_name' => $demo->map_name ?? $video->map_name,
                'player_name' => $demo->player_name ?? $video->player_name,
                'physics' => $demo->physics ?? $video->physics,
                'time_ms' => $demo->time_ms ?? $video->time_ms,
                'gametype' => $demo->gametype ?? $video->gametype,
                'record_id' => $recordId,
                'youtube_url' => $validated['youtube_url'],
                'youtube_video_id' => $youtubeVideoId,
                'render_duration_seconds' => $validated['render_duration_seconds'] ?? $video->render_duration_seconds,
                'video_file_size' => $validated['video_file_size'] ?? $video->video_file_size,
                'is_visible' => true,
                'published_at' => now(),
                'publish_approved' => true,
                'failure_reason' => null,
            ]);

            Log::info('reportByHash: updated existing Discord placeholder', [
                'video_id' => $video->id,
                'demo_id' => $demo->id,
            ]);

            return response()->json(['success' => true, 'id' => $video->id, 'updated' => true]);
        }

        // No placeholder found - create fresh record (backward compat for legacy / missed start calls)
        $video = RenderedVideo::create([
            'map_name' => $demo->map_name ?? 'Unknown',
            'player_name' => $demo->player_name ?? 'Unknown',
            'physics' => $demo->physics,
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'record_id' => $recordId,
            'demo_id' => $demo->id,
            'source' => 'discord',
            'requested_by' => $validated['requested_by'] ?? null,
            'status' => 'completed',
            'priority' => 1,
            'demo_url' => 'discord://' . $demo->id,
            'demo_filename' => $demo->original_filename,
            'youtube_url' => $validated['youtube_url'],
            'youtube_video_id' => $youtubeVideoId,
            'render_duration_seconds' => $validated['render_duration_seconds'] ?? null,
            'video_file_size' => $validated['video_file_size'] ?? null,
            'is_visible' => true,
            'published_at' => now(),
            'publish_approved' => true,
            'user_id' => $demo->user_id,
        ]);

        Log::info('reportByHash: created RenderedVideo', [
            'video_id' => $video->id,
            'demo_id' => $demo->id,
            'record_id' => $recordId,
            'map_name' => $demo->map_name,
        ]);

        return response()->json(['success' => true, 'id' => $video->id]);
    }

    /**
     * Create a RenderedVideo placeholder at the START of a Discord render so it's
     * visible in the admin panel as 'rendering' immediately (before upload completes).
     * Bot calls this right before launching oDFe. On success, reportByHash updates it
     * to 'completed'. On crash/Ctrl+C, bot calls /fail/{id} to mark it as failed.
     */
    public function startDiscordRender(Request $request)
    {
        $validated = $request->validate([
            'md5_hash' => 'required|string|size:32',
            'requested_by' => 'nullable|string',
            // Metadata from bot (synchronously parsed via DemoCleaner3 in upload-demo).
            // Needed because ProcessDemoJob runs async and demo->map_name may still be null
            // when start-discord-render is called milliseconds after upload-demo.
            'map_name' => 'nullable|string',
            'player_name' => 'nullable|string',
            'physics' => 'nullable|string',
            'time_ms' => 'nullable|integer',
            'gametype' => 'nullable|string',
        ]);

        $demo = UploadedDemo::where('file_hash', $validated['md5_hash'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$demo) {
            Log::warning('startDiscordRender: no demo found for hash', ['md5_hash' => $validated['md5_hash']]);
            return response()->json(['success' => false, 'error' => 'No demo found for this hash'], 404);
        }

        // Merge bot-provided metadata with demo fields (demo fields may still be null
        // if ProcessDemoJob hasn't finished yet). Provide hard fallbacks for NOT NULL cols.
        $mapName = $validated['map_name'] ?? $demo->map_name ?? 'Pending';
        $playerName = $validated['player_name'] ?? $demo->player_name ?? 'Pending';
        $physics = $validated['physics'] ?? $demo->physics;
        $timeMs = $validated['time_ms'] ?? $demo->time_ms;
        $gametype = $validated['gametype'] ?? $demo->gametype;

        // If there's already a rendering placeholder for this demo, reuse it (bot retry scenario).
        $existing = RenderedVideo::where('demo_id', $demo->id)
            ->where('source', 'discord')
            ->whereIn('status', ['rendering', 'uploading'])
            ->whereNull('youtube_video_id')
            ->orderBy('id', 'desc')
            ->first();

        if ($existing) {
            $existing->update([
                'status' => 'rendering',
                'map_name' => $mapName,
                'player_name' => $playerName,
                'physics' => $physics,
                'time_ms' => $timeMs,
                'gametype' => $gametype,
                'failure_reason' => null,
                'updated_at' => now(),
            ]);
            return response()->json(['success' => true, 'id' => $existing->id, 'reused' => true]);
        }

        $video = RenderedVideo::create([
            'map_name' => $mapName,
            'player_name' => $playerName,
            'physics' => $physics,
            'time_ms' => $timeMs,
            'gametype' => $gametype,
            'record_id' => $demo->record_id,
            'demo_id' => $demo->id,
            'source' => 'discord',
            'requested_by' => $validated['requested_by'] ?? null,
            'status' => 'rendering',
            // Discord demos are user-requested on-demand, so they're P1 (highest).
            // This is cosmetic for admin panel display; Discord renders never go
            // through the queue endpoint that actually orders by priority.
            'priority' => 1,
            // demo_url is NOT NULL in the schema but Discord demos don't have a URL
            // (they were uploaded directly). Use a placeholder that identifies the source.
            'demo_url' => 'discord://' . $demo->id,
            'demo_filename' => $demo->original_filename,
            'is_visible' => false,
            'user_id' => $demo->user_id,
        ]);

        Log::info('startDiscordRender: created placeholder', [
            'video_id' => $video->id,
            'demo_id' => $demo->id,
            'map_name' => $mapName,
        ]);

        return response()->json(['success' => true, 'id' => $video->id]);
    }

    /**
     * Return the one-shot "restart Discord scraping from message X" marker set by
     * an admin in Filament, and clear it. Bot calls this on startup and, if a value
     * comes back, resets its local channels.last_scraped_message_id to that value
     * before scraping Discord for the next batch of demos.
     *
     * The admin types the message ID they want re-processed. Discord's `after` query
     * is exclusive (returns messages with ID > after), so to make that target message
     * actually included we return (snowflake - 1) as the rewind target. Snowflake IDs
     * are 63-bit and fit in a native PHP int on 64-bit platforms.
     */
    public function discordRestartMarker()
    {
        $key = 'demome:discord_restart_from_message_id';
        $value = SiteSetting::get($key);

        if (!$value) {
            return response()->json(['message_id' => null]);
        }

        // One-shot: clear it so the bot doesn't keep re-applying on every startup.
        SiteSetting::set($key, '');

        // Make the stored ID inclusive: subtract 1 so Discord's exclusive `after`
        // parameter returns the target message itself on the next scrape.
        $rewindTarget = (string) ((int) $value - 1);

        Log::info('discordRestartMarker: consumed marker', [
            'stored_message_id' => $value,
            'rewind_target' => $rewindTarget,
        ]);

        return response()->json([
            'message_id' => $rewindTarget,
            'original' => (string) $value,
        ]);
    }

    /**
     * Return the one-shot "reprocess this single Discord message" marker set
     * by an admin in Filament, and clear it. Unlike discordRestartMarker
     * (which makes the bot rescan from a point forwards), this targets one
     * specific message and only that message - the bot fetches it directly
     * by ID and processes whatever demo attachment(s) it carries.
     *
     * The stored ID is returned verbatim, no -1 rewind, because the bot
     * fetches the message directly rather than using Discord's exclusive
     * `after` parameter.
     */
    public function discordReprocessSingleMessage()
    {
        $key = 'demome:discord_reprocess_single_message_id';
        $value = SiteSetting::get($key);

        if (!$value) {
            return response()->json(['message_id' => null]);
        }

        SiteSetting::set($key, '');

        Log::info('discordReprocessSingleMessage: consumed marker', [
            'message_id' => $value,
        ]);

        return response()->json(['message_id' => (string) $value]);
    }

    /**
     * A client-supplied file date (unix seconds) as a timestamp, or null.
     *
     * Clamped to now: a clock running ahead would write a date in the future,
     * and a future date passes every "was this made after the ballot opened"
     * check there is.
     */
    private function clientMtime($seconds): ?Carbon
    {
        if (! $seconds) {
            return null;
        }

        $at = Carbon::createFromTimestamp((int) $seconds);

        return $at->isFuture() ? now() : $at;
    }

    public function uploadDemo(Request $request)
    {
        $request->validate([
            'demo' => 'required|file|max:524288',
            'discord_author' => 'nullable|string',
            'discord_message_id' => 'nullable|string',
            'discord_channel_id' => 'nullable|string',
            // Unix seconds, if the caller knows when the file was written. The
            // bot usually does not - a Discord attachment carries no date - but
            // the field exists so this path is not the one hole in a rule the
            // other four upload routes now fill in.
            'file_mtime' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('demo');
        $originalFilename = $file->getClientOriginalName();

        // Check duplicate by hash. withUnreleasedComps() because file_hash is
        // unique - a comps entry hidden from this query would come back as a
        // constraint violation on the insert below instead of a clean answer.
        $hash = md5_file($file->getRealPath());
        $existing = UploadedDemo::withUnreleasedComps()
            ->where('file_hash', $hash)
            ->whereNotIn('status', ['failed', 'failed-validity', 'unsupported-version'])
            ->first();

        // Seeing the row is not the same as being allowed to describe it. This
        // endpoint answers a render request, and rendering a comps entry would
        // put a run still being competed on onto YouTube - so a held entry gets
        // a refusal that says nothing about the map, the player or the time.
        if ($existing && $existing->isHeldForComps()) {
            return response()->json([
                'success' => false,
                'error' => 'This demo is entered in a comps round that is still running.',
            ], 409);
        }

        if ($existing) {
            // If this demo already has a completed render on YouTube, surface
            // that here so the bot can short-circuit and just reply with the
            // existing URL instead of re-rendering / re-uploading.
            $completedVideo = RenderedVideo::where('demo_id', $existing->id)
                ->where('status', 'completed')
                ->whereNotNull('youtube_video_id')
                ->orderBy('id', 'desc')
                ->first();

            $payload = [
                'success' => true,
                'duplicate' => true,
                'demo_id' => $existing->id,
                'map_name' => $existing->map_name,
                'player_name' => $existing->player_name,
                'physics' => $existing->physics,
                'time_ms' => $existing->time_ms,
                'gametype' => $existing->gametype,
                'record_id' => $existing->record_id,
            ];

            if ($completedVideo) {
                $payload['existing_video'] = [
                    'id'               => $completedVideo->id,
                    'youtube_video_id' => $completedVideo->youtube_video_id,
                    'youtube_url'      => $completedVideo->youtube_url,
                    'source'           => $completedVideo->source,
                ];
            }

            return response()->json($payload);
        }

        // Create UploadedDemo record
        // Only a date the caller states outright. Discord's own timestamps are
        // deliberately not used: they say when the file was posted, not when it
        // was recorded, and dating a demo by when somebody happened to share it
        // would refuse runs for the wrong reason. A demo that arrives this way
        // therefore carries no date and is judged on everything else.
        $mtime = $this->clientMtime($request->input('file_mtime'));

        $demo = UploadedDemo::create([
            'original_filename' => $originalFilename,
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'status' => 'uploaded',
            'source' => UploadedDemo::SOURCE_DEMOME,
            'client_file_mtime' => $mtime,
        ]);

        // Store file locally - use Storage::put with file contents (Octane/Swoole compatible)
        $tempDir = "demos/temp/{$demo->id}";
        $storedPath = $tempDir . '/' . $originalFilename;
        $fileContents = file_get_contents($file->getRealPath());

        if ($fileContents === false) {
            Log::error('Demome upload: failed to read uploaded file', [
                'demo_id' => $demo->id,
                'filename' => $originalFilename,
                'upload_tmp' => $file->getRealPath(),
            ]);
            $demo->update(['status' => 'failed', 'processing_output' => 'Failed to read uploaded file from temp']);
            return response()->json(['success' => false, 'error' => 'Failed to read uploaded file'], 500);
        }

        $stored = Storage::disk('local')->put($storedPath, $fileContents);

        if (!$stored) {
            Log::error('Demome upload: Storage::put failed', [
                'demo_id' => $demo->id,
                'filename' => $originalFilename,
                'stored_path' => $storedPath,
            ]);
            $demo->update(['status' => 'failed', 'processing_output' => 'File storage failed - Storage::put returned false']);
            return response()->json(['success' => false, 'error' => 'File storage failed'], 500);
        }

        $fullStoredPath = storage_path('app/' . $storedPath);
        Log::info('Demome upload: file stored', [
            'demo_id' => $demo->id,
            'stored_path' => $storedPath,
            'full_path' => $fullStoredPath,
            'file_exists' => file_exists($fullStoredPath),
            'file_size' => filesize($fullStoredPath),
        ]);

        $demo->update(['file_path' => $storedPath]);

        // Dispatch processing job (async - compression, Backblaze upload, matching)
        \App\Jobs\ProcessDemoJob::dispatch($demo);

        // Synchronously parse metadata for immediate response
        try {
            $processSingleScript = base_path('app/Services/DemoProcessor/bin/process_single_demo.py');
            $fullPath = storage_path('app/' . $storedPath);

            $process = new \Symfony\Component\Process\Process(
                ['python3', '-W', 'ignore', $processSingleScript, $fullPath, '--json'],
                dirname($processSingleScript),
            );
            $process->setTimeout(60);
            $process->run();

            $output = trim($process->getOutput());
            $jsonData = json_decode($output, true);

            if ($jsonData && isset($jsonData['map_name'])) {
                $physicsParts = explode('.', strtoupper($jsonData['physics'] ?? ''));
                $physics = $physicsParts[1] ?? ($physicsParts[0] ?? null);

                return response()->json([
                    'success' => true,
                    'demo_id' => $demo->id,
                    'map_name' => $jsonData['map_name'],
                    'player_name' => $jsonData['player_name'] ?? null,
                    'physics' => $physics,
                    'time_ms' => isset($jsonData['time_seconds']) ? (int)($jsonData['time_seconds'] * 1000) : null,
                    'gametype' => $physicsParts[0] ?? null,
                    'record_id' => null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Demome upload: sync parse failed for {$originalFilename}: {$e->getMessage()}");
        }

        // Fallback: parse from filename
        $metadata = $this->parseFilename($originalFilename);

        return response()->json([
            'success' => true,
            'demo_id' => $demo->id,
            'map_name' => $metadata['map_name'],
            'player_name' => $metadata['player_name'],
            'physics' => $metadata['physics'],
            'time_ms' => $metadata['time_ms'],
            'gametype' => $metadata['gametype'],
            'record_id' => null,
        ]);
    }

    public function downloadDemo(UploadedDemo $demo)
    {
        if (empty($demo->file_path)) {
            return response()->json(['error' => 'Demo file not available'], 404);
        }

        $filename = $demo->processed_filename ?: $demo->original_filename;

        // Check if stored locally or on Backblaze
        $isLocal = str_starts_with($demo->file_path, 'demos/temp/') ||
                   str_starts_with($demo->file_path, 'demos/failed/');

        if ($isLocal) {
            $fullPath = storage_path("app/{$demo->file_path}");
            if (!file_exists($fullPath)) {
                return response()->json(['error' => 'Demo file not found'], 404);
            }
            $contents = file_get_contents($fullPath);
        } else {
            try {
                $contents = Storage::get($demo->file_path);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to retrieve demo from storage'], 500);
            }
        }

        if (!$contents) {
            return response()->json(['error' => 'Empty demo file'], 404);
        }

        // Try to extract from 7z archive
        $extracted = $this->extractFromArchive($contents, $filename);
        if ($extracted) {
            return response()->streamDownload(function() use ($extracted) {
                echo $extracted['contents'];
            }, $extracted['filename'], [
                'Content-Type' => 'application/octet-stream',
            ]);
        }

        // Return raw file
        return response()->streamDownload(function() use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function extractFromArchive($contents, $filename)
    {
        // Check for 7z magic bytes
        if (strlen($contents) < 6 || substr($contents, 0, 6) !== "7z\xBC\xAF\x27\x1C") {
            return null;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'demo_');
        file_put_contents($tempFile, $contents);

        $tempDir = $tempFile . '_extracted';
        mkdir($tempDir);

        try {
            $process = new \Symfony\Component\Process\Process(
                ['7z', 'x', '-o' . $tempDir, '-y', $tempFile]
            );
            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful()) {
                $files = glob($tempDir . '/*');
                if (!empty($files)) {
                    $extractedFile = $files[0];
                    $extractedContents = file_get_contents($extractedFile);
                    $extractedFilename = basename($extractedFile);

                    // Clean up
                    array_map('unlink', glob($tempDir . '/*'));
                    rmdir($tempDir);
                    unlink($tempFile);

                    return [
                        'contents' => $extractedContents,
                        'filename' => $extractedFilename,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to extract 7z archive: {$e->getMessage()}");
        }

        // Clean up on failure
        if (is_dir($tempDir)) {
            array_map('unlink', glob($tempDir . '/*'));
            @rmdir($tempDir);
        }
        @unlink($tempFile);

        return null;
    }

    public function videosToPublish()
    {
        $videos = RenderedVideo::where('status', 'completed')
            ->where('publish_approved', true)
            ->whereNull('published_at')
            ->whereNotNull('youtube_video_id')
            ->select('id', 'youtube_video_id', 'map_name', 'player_name')
            ->limit(1000)
            ->get();

        return response()->json(['videos' => $videos]);
    }

    public function markPublished(RenderedVideo $renderedVideo)
    {
        $renderedVideo->update(['published_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function lookupByHash(string $hash)
    {
        $demo = UploadedDemo::where('file_hash', $hash)
            ->whereNotIn('status', ['failed', 'failed-validity', 'unsupported-version'])
            ->first();

        if (!$demo) {
            return response()->json(['success' => false, 'message' => 'No demo found with this hash']);
        }

        // Check if there's already a RenderedVideo for this demo
        $existingVideo = RenderedVideo::where('demo_id', $demo->id)
            ->where('status', 'completed')
            ->first();

        // Also check by record_id if no demo_id match
        if (!$existingVideo && $demo->record_id) {
            $existingVideo = RenderedVideo::where('record_id', $demo->record_id)
                ->where('status', 'completed')
                ->first();
        }

        // Build a RenderedVideo-like instance for metadata generation. We use
        // an unsaved RenderedVideo (not stdClass) because VideoMetadataService
        // has a strict RenderedVideo type hint and crashes the request with a
        // 500 if you hand it anything else - which is exactly how the bot's
        // dedup short-circuit silently fell through on 2026-05-19 and caused
        // duplicate renders/uploads.
        $metaItem = new RenderedVideo([
            'map_name'      => $demo->map_name,
            'player_name'   => $demo->player_name,
            'physics'       => $demo->physics,
            'time_ms'       => $demo->time_ms,
            'gametype'      => $demo->gametype,
            'record_id'     => $demo->record_id,
            'demo_id'       => $demo->id,
            'demo_filename' => $demo->processed_filename ?? $demo->original_filename,
        ]);

        // Defense in depth: even if VideoMetadataService blows up for some
        // edge case (missing demo metadata, content filter quirk, etc.), we
        // must still return the existing_video block - that is what the bot
        // relies on for dedup. Title/description/tags are nice-to-have.
        $safeMeta = function (callable $fn, $default) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                \Log::warning('lookupByHash: metadata generation failed', [
                    'error' => $e->getMessage(),
                ]);
                return $default;
            }
        };

        $response = [
            'success' => true,
            'demo_id' => $demo->id,
            'map_name' => $demo->map_name,
            'player_name' => $demo->player_name,
            'physics' => $demo->physics,
            'time_ms' => $demo->time_ms,
            'gametype' => $demo->gametype,
            'record_id' => $demo->record_id,
            'demo_filename' => $demo->processed_filename ?? $demo->original_filename,
            'download_url' => "https://defrag.racing/demos/{$demo->id}/download",
            'video_title' => $safeMeta(fn () => \App\Services\VideoMetadataService::generateTitle($metaItem), null),
            'video_description' => $safeMeta(fn () => \App\Services\VideoMetadataService::generateDescription($metaItem), null),
            'video_tags' => $safeMeta(fn () => \App\Services\VideoMetadataService::generateTags($metaItem), []),
        ];

        if ($existingVideo) {
            $response['existing_video'] = [
                'id' => $existingVideo->id,
                'youtube_video_id' => $existingVideo->youtube_video_id,
                'youtube_url' => $existingVideo->youtube_url,
                'source' => $existingVideo->source,
            ];
        }

        return response()->json($response);
    }

    public function swapVideo(RenderedVideo $renderedVideo, Request $request)
    {
        $validated = $request->validate([
            'youtube_url' => 'required|string',
            'youtube_video_id' => 'required|string|max:20',
            'published_at' => 'nullable|date',
        ]);

        $oldYoutubeId = $renderedVideo->youtube_video_id;

        $renderedVideo->update([
            'youtube_url' => $validated['youtube_url'],
            'youtube_video_id' => $validated['youtube_video_id'],
            'published_at' => $validated['published_at'] ?? $renderedVideo->published_at,
        ]);

        return response()->json([
            'success' => true,
            'old_youtube_video_id' => $oldYoutubeId,
        ]);
    }

    private function parseFilename(string $filename): array
    {
        $result = [
            'map_name' => null,
            'player_name' => null,
            'physics' => null,
            'time_ms' => null,
            'gametype' => null,
        ];

        // Pattern: mapname[gametype.physics]mm.ss.mmm(player.country).dm_68
        if (preg_match('/([^[]+)\[([^.]+)\.([^\]]+)\](\d+)\.(\d+)\.(\d+)\(([^.]+)/', $filename, $m)) {
            $result['map_name'] = $m[1];
            $result['gametype'] = $m[2];
            $result['physics'] = strtoupper($m[3]);
            $result['time_ms'] = ((int)$m[4] * 60000) + ((int)$m[5] * 1000) + (int)$m[6];
            $result['player_name'] = $m[7];
        }

        return $result;
    }

    public function autoApprovePublish(Request $request)
    {
        // Server-side daily publish cap (overrides whatever the bot asks for, so
        // the daily volume is controlled here without touching the bot). Counts
        // what already counts toward today: published today + approved-today but
        // not yet published, so repeated 4h bot calls can't overshoot.
        $dailyTarget = (int) \App\Models\SiteSetting::get('demome:daily_publish_target', 8);

        $publishedToday = RenderedVideo::whereDate('published_at', today())->count();
        $approvedTodayPending = RenderedVideo::where('publish_approved', true)
            ->whereNull('published_at')
            ->whereDate('updated_at', today())
            ->count();
        $remaining = max(0, $dailyTarget - $publishedToday - $approvedTodayPending);

        $count = min((int) $request->input('count', 2), 5, $remaining);

        if ($count <= 0) {
            return response()->json(['approved' => 0, 'reason' => 'daily target reached', 'daily_target' => $dailyTarget]);
        }

        $videos = \App\Services\RenderQueueService::getNextPublishBatch($count);

        $approved = 0;
        foreach ($videos as $video) {
            $video->update(['publish_approved' => true]);
            $approved++;
        }

        return response()->json(['approved' => $approved]);
    }

    /**
     * The playlists the admin has queued, each with the videos it should hold.
     *
     * Sent whole rather than paged: a playlist is a few thousand ids at most,
     * and the bot has to see the entire list before it can work out what to
     * add and what to take out.
     */
    public function playlistsToSync(Request $request)
    {
        $service = new \App\Services\YoutubePlaylistService();
        $definitions = $service->definitions();

        // `?all=1` hands back every playlist that has been created, queued or
        // not. The recovery pass needs the full wanted list to compare against
        // what YouTube actually holds, and a finished playlist has already
        // left the queue.
        $playlists = \App\Models\YoutubePlaylist::query()
            ->when(! $request->boolean('all'), fn ($q) => $q->where('sync_queued', true))
            ->when($request->boolean('all'), fn ($q) => $q->whereNotNull('youtube_playlist_id'))
            ->orderBy('key')
            ->get();

        $out = [];

        foreach ($playlists as $playlist) {
            if (! isset($definitions[$playlist->key])) {
                continue;
            }

            $videoIds = \App\Models\YoutubePlaylistItem::where('youtube_playlist_id', $playlist->id)
                ->join('rendered_videos', 'rendered_videos.id', '=', 'youtube_playlist_items.rendered_video_id')
                ->orderBy('youtube_playlist_items.position')
                ->pluck('rendered_videos.youtube_video_id')
                ->filter()
                ->values();

            $out[] = [
                'key' => $playlist->key,
                'title' => $definitions[$playlist->key]['title'],
                'description' => $service->description($playlist->key),
                'youtube_playlist_id' => $playlist->youtube_playlist_id,
                'video_ids' => $videoIds,
            ];
        }

        return response()->json(['playlists' => $out]);
    }

    /**
     * Remember the id YouTube gave a playlist the bot has just created. Without
     * this the next run has no way to tell an existing playlist from a missing
     * one and would make a second copy of every shelf.
     */
    public function playlistCreated(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string',
            'youtube_playlist_id' => 'required|string',
        ]);

        $playlist = \App\Models\YoutubePlaylist::where('key', $data['key'])->firstOrFail();
        $playlist->update(['youtube_playlist_id' => $data['youtube_playlist_id']]);

        return response()->json(['success' => true]);
    }

    /**
     * A playlist the bot has finished with. It leaves the queue, and the count
     * it reports is what YouTube actually holds, not what was planned - the two
     * come apart when a run stops on a dead quota.
     */
    public function playlistSynced(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string',
            'count' => 'required|integer|min:0',
        ]);

        $playlist = \App\Models\YoutubePlaylist::where('key', $data['key'])->firstOrFail();

        $playlist->update([
            'sync_queued' => false,
            'synced_count' => $data['count'],
            'synced_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Put a video back in the render queue because YouTube no longer has it.
     *
     * Everything YouTube gave us is cleared: the id, the url and the published
     * date. Leaving the id on the row would keep the site pointing at a video
     * nobody can open, and would keep the playlists trying to add it.
     *
     * The demo stays, and it is the whole point - the row goes back to pending
     * with the demo it was made from, so the bot renders it again from scratch.
     *
     * The caller must have checked with videos.list first. One 404 from an
     * insert is not proof: the API answers nothing for a video that is fine
     * often enough that youtubeweb.py waits for five in a row before it
     * believes it, and a reset on a single refusal would re-render thousands
     * of videos that are sitting on the channel.
     */
    public function videoGone(Request $request)
    {
        $data = $request->validate([
            'youtube_video_ids' => 'required|array|max:500',
            'youtube_video_ids.*' => 'string|max:20',
        ]);

        $videos = RenderedVideo::whereIn('youtube_video_id', $data['youtube_video_ids'])->get();

        $requeued = [];
        $skipped = [];

        foreach ($videos as $video) {
            // A blocked map is not put back in the queue. The row would sit
            // there as `pending` for ever, because the queue skips blocked maps
            // when it picks what to render next. Two of the first videos found
            // gone were on `chile` and `1strank4free`, both blocked, so this is
            // the ordinary case and not a corner of one.
            $blocked = JokeMaps::isJoke($video->map_name, $video->physics);

            $renderable = ! $blocked && ($video->demo_url || $video->demo_id);

            // Named, not counted. A run that resets rows has to say which rows,
            // or there is no way to tell a good pass from a bad one afterwards.
            $named = [
                'id' => $video->id,
                'youtube_video_id' => $video->youtube_video_id,
                'map' => $video->map_name,
                'player' => $video->player_name,
                'physics' => $video->physics,
                'time_ms' => $video->time_ms,
            ];

            if ($renderable) {
                $requeued[] = $named;
            } else {
                // No demo, nothing to render from. Cleared anyway, because the
                // link is dead either way.
                $skipped[] = $named;
            }

            $video->update([
                'status' => $renderable ? 'pending' : 'failed',
                'youtube_video_id' => null,
                'youtube_url' => null,
                'published_at' => null,
                'publish_approved' => false,
                'render_duration_seconds' => null,
                'video_file_size' => null,
                'failure_reason' => match (true) {
                    $renderable => null,
                    $blocked => 'Video gone from YouTube. The map gets no video, so it was not queued again.',
                    default => 'Video gone from YouTube and no demo left to render it from.',
                },
            ]);
        }

        return response()->json([
            'requeued' => $requeued,
            'no_demo' => $skipped,
            'not_found' => array_values(array_diff(
                $data['youtube_video_ids'],
                $videos->pluck('youtube_video_id')->all()
            )),
        ]);
    }

    public function publishCountsToday()
    {
        return response()->json([
            'published_today' => RenderedVideo::where('status', 'completed')
                ->whereNotNull('published_at')
                ->whereDate('published_at', today())
                ->count(),
        ]);
    }

    public function uploadCountsToday()
    {
        $today = now()->toDateString();

        $total = RenderedVideo::where('status', 'completed')
            ->whereNotNull('youtube_url')
            ->whereDate('updated_at', $today)
            ->count();

        $auto = RenderedVideo::where('status', 'completed')
            ->whereNotNull('youtube_url')
            ->where('source', 'auto')
            ->whereDate('updated_at', $today)
            ->count();

        $web = RenderedVideo::where('status', 'completed')
            ->whereNotNull('youtube_url')
            ->where('source', 'web')
            ->whereDate('updated_at', $today)
            ->count();

        $discord = RenderedVideo::where('status', 'completed')
            ->whereNotNull('youtube_url')
            ->where('source', 'discord')
            ->whereDate('updated_at', $today)
            ->count();

        return response()->json([
            'total' => $total,
            'auto' => $auto,
            'web' => $web,
            'discord' => $discord,
        ]);
    }

    /**
     * Rolling N-hour upload count + when the oldest upload in the window expires.
     * Bot uses this for a proactive throttle that respects YouTube's rolling 24h
     * channel-level cap (uploadLimitExceeded), which `uploadCountsToday` doesn't
     * catch - that one resets at local midnight, YouTube's cap doesn't.
     */
    public function recentUploadCount(Request $request)
    {
        $hours = max(1, min(168, (int) $request->input('hours', 24)));
        $since = Carbon::now()->subHours($hours);

        $base = RenderedVideo::where('status', 'completed')
            ->whereNotNull('youtube_url')
            ->where('updated_at', '>=', $since);

        $count = (clone $base)->count();
        $oldest = (clone $base)->orderBy('updated_at', 'asc')->first();

        return response()->json([
            'count' => $count,
            'hours' => $hours,
            'oldest_at' => $oldest?->updated_at?->toIso8601String(),
            'oldest_expires_at' => $oldest?->updated_at?->addHours($hours)->toIso8601String(),
        ]);
    }

    /**
     * Every row whose rendered mp4 could still be sitting on the demome PC.
     *
     * The mp4 is only deleted after an upload AND YouTube-side processing both
     * confirm, so anything that failed on either half leaves its file behind -
     * and nothing on this side knows the file is there. Only the bot can see
     * the disk, and it derives the mp4 name from `demo_filename`, so the match
     * has to happen there: this endpoint just hands over the rows cheaply and
     * the bot keeps the ones it can find a file for.
     *
     * Both halves are needed. A `failed` row means the video never landed and
     * the file is worth re-uploading; a `completed` row whose upload timed out
     * waiting for YouTube means the video IS up and the file is pure waste.
     */
    public function localFileCandidates(Request $request)
    {
        $since = $request->input('since');
        $sinceDate = $since ? Carbon::parse($since) : now()->subDays(365);
        // Newest first, so a collection that hits the ceiling loses its oldest
        // rows - the ones least likely to still have a file - and says so
        // instead of quietly answering "nothing here" for the rest.
        $limit = 20000;

        $videos = RenderedVideo::query()
            ->toBase()
            ->whereNotNull('demo_filename')
            ->where('demo_filename', '!=', '')
            ->whereIn('status', ['failed', 'upload_pending', 'completed'])
            // Failed rows are few and a file can outlive the window it was
            // rendered in, so they come whatever their age. Completed rows are
            // ten thousand and only the recent ones can still have a file.
            ->where(fn ($query) => $query
                ->whereIn('status', ['failed', 'upload_pending'])
                ->orWhere('updated_at', '>=', $sinceDate))
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'demo_filename', 'status', 'source', 'youtube_video_id', 'failure_reason', 'updated_at'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'demo_filename' => $item->demo_filename,
                'status' => $item->status,
                'source' => $item->source,
                'youtube_video_id' => $item->youtube_video_id,
                // Carries the id of a video that WAS uploaded. A row fails with
                // "YouTube processing failed for video X" when the status check
                // timed out or transiently answered nothing, and those videos
                // are routinely fine on the channel afterwards - so the file on
                // the bot's disk is often waste rather than work to redo. The
                // bot reads the id back out and asks YouTube before uploading
                // tens of gigabytes a second time.
                'failure_reason' => $item->failure_reason,
                'updated_at' => $item->updated_at,
            ]);

        return response()->json([
            'since' => $sinceDate->toIso8601String(),
            'count' => $videos->count(),
            'capped' => $videos->count() >= $limit,
            'videos' => $videos,
        ]);
    }

    /**
     * Get video metadata (title, description, tags) for a specific video.
     * Used by bot to get correct metadata before upload.
     */
    public function videoMetadata(RenderedVideo $renderedVideo)
    {
        return response()->json([
            'title' => \App\Services\VideoMetadataService::generateTitle($renderedVideo),
            'description' => \App\Services\VideoMetadataService::generateDescription($renderedVideo),
            'tags' => \App\Services\VideoMetadataService::generateTags($renderedVideo),
        ]);
    }

    /**
     * Get batch of videos needing YouTube metadata update.
     * Bot calls this, updates YouTube, then marks as updated.
     */
    public function videosNeedingMetadataUpdate(Request $request)
    {
        $lastId = (int) $request->input('after_id', 0);
        $limit = min((int) $request->input('limit', 20), 50);

        $videos = RenderedVideo::where('status', 'completed')
            ->whereNotNull('youtube_video_id')
            ->whereNotNull('youtube_url')
            // Newest first when before_id is given (0 = start at the newest).
            // A backfill costs API quota per video and there are ten thousand
            // of them, so it has to reach the ones people actually watch first;
            // walking up from id 1 spends days on videos nobody opens.
            ->when($request->has('before_id'), function ($query) use ($request) {
                $beforeId = (int) $request->input('before_id');

                return $query->when($beforeId > 0, fn ($q) => $q->where('id', '<', $beforeId))
                    ->orderBy('id', 'desc');
            }, fn ($query) => $query->where('id', '>', $lastId)->orderBy('id', 'asc'))
            ->limit($limit)
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'youtube_video_id' => $video->youtube_video_id,
                    'title' => \App\Services\VideoMetadataService::generateTitle($video),
                    'description' => \App\Services\VideoMetadataService::generateDescription($video),
                    'tags' => \App\Services\VideoMetadataService::generateTags($video),
                ];
            });

        return response()->json(['videos' => $videos]);
    }
}
