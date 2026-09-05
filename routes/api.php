<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Desktop launcher API. Sanctum personal access tokens issued at
// /user/launcher-tokens with abilities ["launcher:upload","launcher:read"].
// Three throttle buckets, one per workload shape, so traffic in one
// section can't starve another:
//
//  - launcher-lookup (6000/min = 100/sec): lookup-by-hash ONLY.
//    Rescans hammer this; isolated so a Faster-button burst can't
//    affect browse endpoints.
//
//  - launcher-browse (600/min = 10/sec): servers, notifications,
//    records, maps, me. User-initiated UI; reserved from the
//    lookup budget so the launcher UI stays responsive even during
//    a heavy rescan.
//
//  - launcher-upload (300/min = 5/sec): upload-demo only. Multipart
//    upload + ProcessDemoJob dispatch. 7 workers at ~1-2s/job give
//    us ~210-420 jobs/min sustained. Pending jobs queue and drain
//    at worker rate.
//
// `log.api` writes every call to api_call_log for the same audit trail
// the post-incident /api lockdown added, applied here proactively.
//
// `withoutMiddleware('throttle:api')` is critical: the api middleware
// group applies `throttle:api` (60/min per user) to every route under
// /api, which would otherwise dominate over the per-route launcher
// throttles below. Demome routes already drop it for the same reason
// (their headless renderer would burn through 60/min in seconds).
// Without this line the launcher-read 6000/min limit was a no-op and
// rescans got 429ed at the 60-call mark.
Route::prefix('launcher')
    ->middleware(['auth:sanctum', 'log.api'])
    ->withoutMiddleware('throttle:api')
    ->group(function () {
        Route::middleware(['abilities:launcher:upload', 'throttle:launcher-upload'])->group(function () {
            Route::post('/upload-demo', [\App\Http\Controllers\Api\LauncherController::class, 'uploadDemo']);

            // Entering a run. Same bucket as the ordinary upload: it is the
            // same multipart body and the same ProcessDemoJob behind it, and
            // nobody enters a competition faster than they back demos up.
            Route::post('/comps/upload', [\App\Http\Controllers\Api\LauncherController::class, 'compsUpload']);
        });

        // lookup-by-hash is a write-ability route (it's POST and lives
        // alongside upload-demo conceptually) but lives in its OWN
        // throttle bucket (launcher-lookup) so a rescan burst can't
        // burn through the budget that gates the browse endpoints
        // below.
        Route::middleware(['abilities:launcher:upload', 'throttle:launcher-lookup'])->group(function () {
            Route::post('/lookup-by-hash', [\App\Http\Controllers\Api\LauncherController::class, 'lookupByHash']);
        });

        Route::middleware(['abilities:launcher:read', 'throttle:launcher-browse'])->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\LauncherController::class, 'me']);
            Route::get('/servers', [\App\Http\Controllers\Api\LauncherController::class, 'servers']);
            Route::get('/notifications', [\App\Http\Controllers\Api\LauncherController::class, 'notifications']);
            Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\LauncherController::class, 'notificationsUnreadCount']);
            Route::get('/records', [\App\Http\Controllers\Api\LauncherController::class, 'records']);
            Route::get('/maps', [\App\Http\Controllers\Api\LauncherController::class, 'maps']);
            Route::get('/render-status', [\App\Http\Controllers\Api\LauncherController::class, 'renderStatus']);

            // What comps is playing. The launcher polls this every few minutes
            // and uses the map names to keep a run on this week's map out of
            // the ordinary upload path, so it is read-shaped but load-bearing.
            Route::get('/comps', [\App\Http\Controllers\Api\LauncherController::class, 'comps']);
            Route::get('/rendered-index', [\App\Http\Controllers\Api\LauncherController::class, 'renderedIndex']);

            // Mark-as-read / mark-as-unread for the launcher Notifications
            // tab. Per-row toggle covers normal interaction; bulk endpoints
            // back the "Mark all as read/unread" buttons so a user with 50
            // unread doesn't fire 50 round-trips. Same throttle bucket as
            // the read endpoints - all mutations here are tiny single-row
            // updates (or one UPDATE for the bulk variants).
            Route::post('/notifications/records/{id}/toggle', [\App\Http\Controllers\Api\LauncherController::class, 'notificationRecordToggle']);
            Route::post('/notifications/records/mark-read', [\App\Http\Controllers\Api\LauncherController::class, 'notificationRecordsMarkRead']);
            Route::post('/notifications/records/mark-unread', [\App\Http\Controllers\Api\LauncherController::class, 'notificationRecordsMarkUnread']);
            Route::post('/notifications/system/{id}/toggle', [\App\Http\Controllers\Api\LauncherController::class, 'notificationSystemToggle']);
            Route::post('/notifications/system/mark-read', [\App\Http\Controllers\Api\LauncherController::class, 'notificationSystemMarkRead']);
            Route::post('/notifications/system/mark-unread', [\App\Http\Controllers\Api\LauncherController::class, 'notificationSystemMarkUnread']);
        });

        // Render-video is a write op (queues a job, costs render farm
        // time) so it requires the upload ability the same way
        // upload-demo does. Stays in the browse bucket because the
        // user-facing rate is "a few clicks per minute" - the
        // 20-per-day quota inside the controller is the real cap, the
        // bucket is just abuse defence.
        Route::middleware(['abilities:launcher:upload', 'throttle:launcher-browse'])->group(function () {
            Route::post('/render-video', [\App\Http\Controllers\Api\LauncherController::class, 'renderVideo']);
        });
    });

// Profile + records AJAX endpoints used by the SPA frontend and (optionally)
// by external clients via personal API tokens. `auth:sanctum,web` accepts
// either a Bearer token in the Authorization header OR a session cookie
// from a logged-in browser. Anonymous requests get a 401 - the frontend
// hides the matching panels client-side so anon profile pages stay clean.
//
// Per-user rate-limit kicks in automatically because `throttle:api` keys
// by user_id when authenticated.
Route::middleware(['auth:sanctum,web', 'log.api'])->group(function () {
    Route::get('/profile/{mddId}/extras', [\App\Http\Controllers\ProfileController::class, 'profileExtras']);
    Route::get('/search-players', [\App\Http\Controllers\ProfileController::class, 'searchPlayers']);
    Route::get('/profile/{userId}/compare/{rivalId}', [\App\Http\Controllers\ProfileController::class, 'comparePlayer']);
    Route::get('/records/search', [\App\Http\Controllers\RecordsController::class, 'search']);
});

// Demome renderer API
Route::prefix('demome')->middleware('demome.token')->withoutMiddleware('throttle:api')->group(function () {
    Route::get('/queue', [\App\Http\Controllers\Api\DemomeController::class, 'queue']);
    Route::post('/claim/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'claim']);
    Route::post('/complete/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'complete']);
    Route::post('/fail/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'fail']);
    Route::post('/reset/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'resetToPending']);
    Route::post('/heartbeat', [\App\Http\Controllers\Api\DemomeController::class, 'heartbeat']);
    Route::post('/report', [\App\Http\Controllers\Api\DemomeController::class, 'report']);
    Route::post('/report-by-hash', [\App\Http\Controllers\Api\DemomeController::class, 'reportByHash']);
    Route::post('/start-discord-render', [\App\Http\Controllers\Api\DemomeController::class, 'startDiscordRender']);
    Route::get('/discord-restart-marker', [\App\Http\Controllers\Api\DemomeController::class, 'discordRestartMarker']);
    Route::get('/discord-reprocess-single-message', [\App\Http\Controllers\Api\DemomeController::class, 'discordReprocessSingleMessage']);
    Route::post('/upload-demo', [\App\Http\Controllers\Api\DemomeController::class, 'uploadDemo']);
    Route::get('/download-demo/{demo}', [\App\Http\Controllers\Api\DemomeController::class, 'downloadDemo']);
    Route::get('/videos-to-publish', [\App\Http\Controllers\Api\DemomeController::class, 'videosToPublish']);
    Route::post('/mark-published/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'markPublished']);
    Route::get('/lookup-by-hash/{hash}', [\App\Http\Controllers\Api\DemomeController::class, 'lookupByHash']);
    Route::post('/swap-video/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'swapVideo']);
    Route::get('/upload-counts-today', [\App\Http\Controllers\Api\DemomeController::class, 'uploadCountsToday']);
    Route::get('/recent-upload-count', [\App\Http\Controllers\Api\DemomeController::class, 'recentUploadCount']);
    Route::get('/publish-counts-today', [\App\Http\Controllers\Api\DemomeController::class, 'publishCountsToday']);
    Route::post('/auto-approve-publish', [\App\Http\Controllers\Api\DemomeController::class, 'autoApprovePublish']);
    Route::get('/playlists-to-sync', [\App\Http\Controllers\Api\DemomeController::class, 'playlistsToSync']);
    Route::post('/playlist-created', [\App\Http\Controllers\Api\DemomeController::class, 'playlistCreated']);
    Route::post('/playlist-synced', [\App\Http\Controllers\Api\DemomeController::class, 'playlistSynced']);
    Route::post('/video-gone', [\App\Http\Controllers\Api\DemomeController::class, 'videoGone']);
    Route::get('/video-metadata/{renderedVideo}', [\App\Http\Controllers\Api\DemomeController::class, 'videoMetadata']);
    Route::get('/local-file-candidates', [\App\Http\Controllers\Api\DemomeController::class, 'localFileCandidates']);
    Route::get('/videos-needing-metadata-update', [\App\Http\Controllers\Api\DemomeController::class, 'videosNeedingMetadataUpdate']);
});

// DefragLive bridge ingest. The Python WebSocket bridge POSTs each chat /
// serverstate broadcast here so the web becomes the source of truth (DB +
// Filament + giveaway), replacing console.json + the cron docker cp. The
// bridge keeps its realtime WS fan-out to the extension untouched. Token-
// guarded server-to-server, exempt from the api throttle like demome.
Route::prefix('defraglive')->middleware('defraglive.token')->withoutMiddleware('throttle:api')->group(function () {
    Route::post('/ingest', [\App\Http\Controllers\Api\DefragliveIngestController::class, 'ingest']);
    // Bot pushes the live game settings in (sync_settings parity).
    Route::post('/sync-settings', [\App\Http\Controllers\Api\DefragliveController::class, 'syncSettings']);
});

// DefragLive extension-facing command surface. Public (the Twitch extension
// calls these), throttled; anything that drives the bot is bot_secret-gated
// inside the controller. Replaces the bridge's inbound settings/command WS
// handling.
Route::prefix('defraglive')->middleware('throttle:120,1')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\Api\DefragliveController::class, 'getSettings']);
    Route::post('/settings', [\App\Http\Controllers\Api\DefragliveController::class, 'applySettings']);
    Route::post('/command', [\App\Http\Controllers\Api\DefragliveController::class, 'command']);
    Route::post('/say', [\App\Http\Controllers\Api\DefragliveController::class, 'say']);
});
