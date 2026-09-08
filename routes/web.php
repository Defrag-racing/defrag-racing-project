<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\WebController;
use App\Http\Controllers\MapsController;
use App\Http\Controllers\MapStatsController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\EndpointController;
use App\Http\Controllers\LauncherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\DemosController;
use App\Http\Controllers\ModelsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\AboutMeController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DefragHQ\DonationManagementController;
use App\Http\Controllers\AliasController;
use App\Http\Controllers\AliasReportController;
use App\Http\Controllers\DemoReportController;
use App\Http\Controllers\YoutubeController;
use App\Http\Controllers\RenderRequestController;
use App\Http\Controllers\CommunityLeaderboardController;
use App\Http\Controllers\DefragliveContestController;
use App\Http\Controllers\DefragliveMapLogController;
use App\Http\Controllers\CommunityTasksController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [WebController::class, 'home'])->name('home');
Route::get('/getting-started', [WebController::class, 'gettingstarted'])->name('getting.started');


Route::post('/search', [SearchController::class, 'search'])->name('search');

// Open to guests on purpose - somebody who cannot read the site yet is
// exactly the person who needs to change its language.
Route::post('/locale', [\App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');

Route::get('/servers', [ServersController::class, 'index'])->name('servers');
Route::get('/api/servers/live', [ServersController::class, 'apiServers'])->name('servers.api');
Route::get('/servers/json', [EndpointController::class, 'index'])->name('servers.json');

// Launcher auto-update manifest - primary endpoint for the desktop
// launcher's tauri-plugin-updater (GH Releases is its fallback). Proxies
// the latest signed `latest.json` from GH with a 5-minute cache so we
// don't pound their CDN on every launcher startup. The mirror exists
// for CN/RU users who have trouble reaching GitHub directly.
Route::get('/launcher/latest.json', [LauncherController::class, 'latestManifest'])->name('launcher.manifest');

Route::get('/launcher', [LauncherController::class, 'page'])->name('launcher');

// Player rules. Static, so no controller - but a real page rather than a CMS
// entry, because people cite rule numbers and those have to stay put.
Route::get('/rules', fn () => Inertia::render('Rules'))->name('rules');

Route::get('/maps', [MapsController::class, 'index'])->name('maps');
Route::get('/maps/filters', [MapsController::class, 'filters'])->name('maps.filters');
Route::get('/maps/random', [MapsController::class, 'random'])->name('maps.random');
Route::get('/maps/stats', [MapStatsController::class, 'index'])->name('maps.stats');

Route::get('/maps/{mapname}/demo-matches', [MapsController::class, 'getDemoMatches'])->name('maps.demoMatches');
Route::get('/maps/{mapname}/time-history', [MapsController::class, 'timeHistory'])->name('maps.timeHistory');
Route::post('/maps/{id}/flag-nsfw', [MapsController::class, 'flagNsfw'])->where('id', '[0-9]+')->middleware(['auth', 'verified'])->name('maps.flag-nsfw');
Route::post('/maps/{id}/unflag-nsfw', [MapsController::class, 'unflagNsfw'])->where('id', '[0-9]+')->middleware(['auth', 'verified'])->name('maps.unflag-nsfw');
Route::post('/maps/{id}/rate-difficulty', [MapsController::class, 'rateDifficulty'])->where('id', '[0-9]+')->middleware('auth')->name('maps.rate-difficulty');
Route::get('/maps/{mapname}', [MapsController::class, 'map'])->name('maps.map');

// Serverdemo validators. The page is public - it explains how reported runs
// are reviewed - while applying needs a verified account.
Route::get('/serverdemo-validators', [\App\Http\Controllers\ServerdemoValidatorController::class, 'index'])->name('serverdemo-validators.index');
Route::post('/serverdemo-validators/apply', [\App\Http\Controllers\ServerdemoValidatorController::class, 'apply'])
    // The throttle counts every POST, including the ones the form itself
    // rejects, so it has to leave room for somebody fumbling the form rather
    // than only for somebody abusing it. Five was not that room.
    ->middleware(['auth', 'verified', 'throttle:20,60'])
    ->name('serverdemo-validators.apply');
Route::post('/serverdemo-validators/vote/{application}', [\App\Http\Controllers\ServerdemoValidatorController::class, 'vote'])
    ->middleware(['auth', 'verified', 'throttle:60,60'])
    ->name('serverdemo-validators.vote');

// The public validation log. Deliberately open to everyone including logged
// out visitors: it exists so that somebody who does not trust us can check
// what was reported and what came of it.
Route::get('/validation-log', [\App\Http\Controllers\ValidationLogController::class, 'index'])->name('validation-log');

// The amnesty: withdrawing your own invalid time. Verified account only - it
// takes a record off the leaderboard, so it has to be a real person's own
// account - and throttled like the other forms.
Route::get('/amnesty', [\App\Http\Controllers\SelfReportController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('amnesty.index');
Route::post('/amnesty', [\App\Http\Controllers\SelfReportController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:30,60'])
    ->name('amnesty.store');

// Wishlist. Reading is public, writing and voting need an account.
Route::get('/wishlist', [\App\Http\Controllers\WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/wishlist', [\App\Http\Controllers\WishlistController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:20,60'])
    ->name('wishlist.store');
// One wish, by id. Nothing is rendered here: it works out which tab the wish
// currently sits under and sends the browser to the list with it highlighted.
// A notification written weeks ago stores only /wishlist/123, so the link
// still lands on the right tab after the wish has moved between them.
Route::get('/wishlist/{wish}', [\App\Http\Controllers\WishlistController::class, 'show'])
    ->name('wishlist.show');
Route::post('/wishlist/{wish}/vote', [\App\Http\Controllers\WishlistController::class, 'vote'])
    ->middleware(['auth', 'verified', 'throttle:120,60'])
    ->name('wishlist.vote');
// Authors ask, they do not delete: by the time a wish is on the list other
// people have voted on it. Removal itself is an admin action in the panel.
// Only the author and an admin may write here. Anybody else who wants the
// same thing files their own wish, which is what keeps the board a list of
// asks rather than a forum.
Route::post('/wishlist/{wish}/reply', [\App\Http\Controllers\WishlistController::class, 'reply'])
    ->middleware(['auth', 'verified'])
    ->name('wishlist.reply');
Route::post('/wishlist/{wish}/request-removal', [\App\Http\Controllers\WishlistController::class, 'requestRemoval'])
    ->middleware(['auth', 'verified', 'throttle:20,60'])
    ->name('wishlist.request-removal');

// Comps. The hub is public - the point is that people can see what is being
// played without an account - and everything that changes something needs one,
// plus a linked MDD profile, because the prize for winning is a wildcard and
// throwaway accounts would otherwise decide the map.
Route::get('/comps', [\App\Http\Controllers\CompsController::class, 'index'])->name('comps.index');
// Settings checker. Reads a demo and throws it away - see DemoCheckController.
// Under /comps because that is the only place the answer matters: the rules it
// checks are the comps rules, and somebody browsing the demo database has no
// question this page answers.
//
// MUST stay above /comps/{comp}, which would otherwise swallow /comps/check
// and look it up as a competition called "check".
//
// Open to everybody on purpose: the people who most need it are the ones who
// have not worked out yet that they need an account for anything. Throttled
// because it runs the parser on whatever it is handed.
Route::get('/comps/check', [\App\Http\Controllers\DemoCheckController::class, 'show'])->name('comps.check');
Route::post('/comps/check', [\App\Http\Controllers\DemoCheckController::class, 'check'])
    ->middleware('throttle:20,1')
    ->name('comps.check.run');
Route::get('/comps/{comp}', [\App\Http\Controllers\CompsController::class, 'show'])->name('comps.show');
// The week's demos in one 7z, once the round is over. Building one is a
// handful of B2 reads and a 7z run, so the throttle is per archive rather
// than per click: a built archive is served from disk.
Route::get('/comps/rounds/{round}/demos/{physics}/{mode}', [\App\Http\Controllers\CompsController::class, 'downloadDemos'])
    ->middleware('throttle:30,1')
    ->name('comps.demos');

Route::post('/comps/rounds/{round}/vote', [\App\Http\Controllers\CompsController::class, 'vote'])
    ->middleware(['auth', 'verified', 'throttle:120,60'])
    ->name('comps.vote');
Route::post('/comps/rounds/{round}/wildcard', [\App\Http\Controllers\CompsController::class, 'useWildcard'])
    ->middleware(['auth', 'verified', 'throttle:20,60'])
    ->name('comps.wildcard');

Route::post('/comps/rounds/{round}/submit', [\App\Http\Controllers\CompSubmissionController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:60,60'])
    ->name('comps.submit');
Route::delete('/comps/submissions/{submission}', [\App\Http\Controllers\CompSubmissionController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('comps.submission.destroy');
Route::post('/comps/submissions/{submission}/report', [\App\Http\Controllers\CompSubmissionController::class, 'reportDemo'])
    ->middleware(['auth', 'verified', 'throttle:20,60'])
    ->name('comps.report-demo');
// Asking about a demo of your own that comps did not take: an unreadable file,
// a run being held. Bound to the demo rather than to an entry, because those
// are the cases where there is no entry.
Route::post('/comps/demos/{demo}/report', [\App\Http\Controllers\CompSubmissionController::class, 'reportOwnDemo'])
    ->middleware(['auth', 'verified', 'throttle:20,60'])
    ->name('comps.report-own-demo');
Route::post('/comps/rounds/{round}/report-map', [\App\Http\Controllers\CompSubmissionController::class, 'reportMap'])
    ->middleware(['auth', 'verified', 'throttle:20,60'])
    ->name('comps.report-map');

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking');
Route::get('/ranking/how-it-works', [RankingController::class, 'howItWorks'])->name('ranking.how-it-works');

Route::get('/community', [CommunityLeaderboardController::class, 'index'])->name('community');

// Signed, permission-checked download for the admin storage browsers
// (Serverdemos Browser + Storage Browser) - streams SFTP -> browser, no disk.
Route::get('/defraghq/storage-download', \App\Http\Controllers\StorageBrowserDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('defraghq.storage-download');

// The demo of ONE reported run, for the validator holding that report. No
// path parameter: the file is looked up from the report itself.
Route::get('/defraghq/validation-demo/{flag}', \App\Http\Controllers\ServerdemoValidationDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('defraghq.validation-demo');

// The serverdemo of a run its own owner withdrew. Admin only - a withdrawal
// is private, and so is the demo that shows which run it was.
Route::get('/defraghq/amnesty-demo/{report}', \App\Http\Controllers\AmnestyDemoDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('defraghq.amnesty-demo');

// DefragLive most-watched-player contest (public leaderboard + raffle odds).
Route::get('/defraglive/contest', [DefragliveContestController::class, 'index'])->name('defraglive.contest');
// OBS Browser Source overlay (transparent top-3 widget) + its JSON feed.
Route::get('/defraglive/contest/overlay', [DefragliveContestController::class, 'overlay'])->name('defraglive.contest.overlay');
Route::get('/defraglive/contest/overlay.json', [DefragliveContestController::class, 'overlayData'])->name('defraglive.contest.overlay.data');
Route::get('/defraglive/maps', [DefragliveMapLogController::class, 'index'])->name('defraglive.maps');
Route::get('/community-tasks', [CommunityTasksController::class, 'index'])->middleware(['auth', 'verified'])->name('community.tasks');
Route::post('/community-tasks/refresh', [CommunityTasksController::class, 'refresh'])->middleware(['auth', 'verified'])->name('community.tasks.refresh');
Route::post('/community-tasks/vote', [CommunityTasksController::class, 'vote'])->middleware(['auth', 'verified'])->name('community.tasks.vote');
Route::post('/community-tasks/skip', [CommunityTasksController::class, 'skip'])->middleware(['auth', 'verified'])->name('community.tasks.skip');
Route::post('/community-tasks/save-session', [CommunityTasksController::class, 'saveSession'])->middleware(['auth', 'verified'])->name('community.tasks.save');
Route::get('/community-tasks/leaderboard', [CommunityTasksController::class, 'fullLeaderboard'])->middleware(['auth', 'verified'])->name('community.tasks.leaderboard');
Route::post('/community-tasks/request-render', [CommunityTasksController::class, 'requestDifficultyRender'])->middleware(['auth', 'verified'])->name('community.tasks.request-render');

Route::get('/rendered-demos', [YoutubeController::class, 'index'])->name('youtube');
Route::redirect('/youtube', '/rendered-demos', 301);
Route::post('/render/request', [RenderRequestController::class, 'store'])->middleware(['auth', 'verified'])->name('render.request');
Route::post('/api/rendered-videos/{id}/report', [RenderRequestController::class, 'reportFailed'])->middleware('auth')->name('render.report');

Route::get('/records', [RecordsController::class, 'index'])->name('records');

// Community downloads hub. The specific segments must stay above the catch-all
// index route, whose optional {id}/{slug} would otherwise swallow them.
Route::get('/downloads/upload', [DownloadsController::class, 'create'])->middleware(['auth', 'verified'])->name('downloads.create');
Route::post('/downloads/upload', [DownloadsController::class, 'store'])->middleware(['auth', 'verified'])->name('downloads.store');
Route::get('/downloads/entry/{download}/{slug?}', [DownloadsController::class, 'show'])->name('downloads.show');
Route::get('/downloads/file/{file}', [DownloadsController::class, 'file'])->name('downloads.file');
Route::get('/downloads/{id?}/{slug?}', [DownloadsController::class, 'index'])->name('downloads');

// Models routes
Route::get('/models', [ModelsController::class, 'index'])->name('models.index');
Route::get('/models/create', [ModelsController::class, 'create'])->middleware(['auth', 'verified'])->name('models.create');
Route::post('/models', [ModelsController::class, 'store'])->middleware(['auth', 'verified'])->name('models.store');
Route::post('/models/temp-upload', [ModelsController::class, 'tempUpload'])->middleware(['auth', 'verified'])->name('models.tempUpload');
Route::post('/models/store-with-gifs', [ModelsController::class, 'storeWithGifs'])->middleware(['auth', 'verified'])->name('models.storeWithGifs');
Route::post('/models/delete-temp', [ModelsController::class, 'deleteTempUpload'])->middleware(['auth', 'verified'])->name('models.deleteTempUpload');
Route::get('/models/bulk-upload', [ModelsController::class, 'bulkUploadForm'])->middleware(['auth', 'verified'])->name('models.bulk-upload');
Route::post('/models/bulk-upload', [ModelsController::class, 'bulkUpload'])->middleware(['auth', 'verified'])->name('models.bulk-upload.store');
Route::get('/models/batch-generate-gifs', [ModelsController::class, 'batchGenerateGifs'])->middleware('auth')->name('models.batchGenerateGifs');
Route::get('/models/{id}/shaders', [ModelsController::class, 'getShaders'])->where('id', '[0-9]+')->name('models.shaders');
Route::get('/models/{id}/download', [ModelsController::class, 'download'])->where('id', '[0-9]+')->name('models.download');
Route::get('/models/{model}/download-extras', [ModelsController::class, 'downloadExtras'])->name('models.downloadExtras');
Route::post('/models/{id}/approve', [ModelsController::class, 'approveModel'])->where('id', '[0-9]+')->middleware('auth')->name('models.approve');
Route::post('/models/{id}/reject', [ModelsController::class, 'rejectModel'])->where('id', '[0-9]+')->middleware('auth')->name('models.reject');
Route::post('/models/{id}/flag-nsfw', [ModelsController::class, 'flagNsfw'])->where('id', '[0-9]+')->middleware(['auth', 'verified'])->name('models.flag-nsfw');
Route::post('/models/{id}/unflag-nsfw', [ModelsController::class, 'unflagNsfw'])->where('id', '[0-9]+')->middleware(['auth', 'verified'])->name('models.unflag-nsfw');
Route::delete('/models/{id}', [ModelsController::class, 'destroyModel'])->where('id', '[0-9]+')->middleware('auth')->name('models.destroy');
Route::get('/models/{id}', [ModelsController::class, 'show'])->where('id', '[0-9]+')->name('models.show');
Route::post('/models/{id}/save-thumbnail', [ModelsController::class, 'saveThumbnail'])->middleware('auth')->name('models.saveThumbnail');
Route::post('/models/{id}/save-head-icon', [ModelsController::class, 'saveHeadIcon'])->middleware('auth')->name('models.saveHeadIcon');
Route::post('/user/confirm-nsfw', [ModelsController::class, 'confirmNsfw'])->middleware('auth')->name('user.confirm-nsfw');
Route::post('/models/{id}/scrape-ws-metadata', [ModelsController::class, 'scrapeWsMetadata'])->middleware('auth')->where('id', '[0-9]+')->name('models.scrapeWsMetadata');
Route::post('/models/{id}/generate-still-thumbnail', [ModelsController::class, 'generateStillThumbnail'])->middleware('auth')->where('id', '[0-9]+')->name('models.generateStillThumbnail');
Route::post('/models/batch-generate-still-thumbnails', [ModelsController::class, 'batchGenerateStillThumbnails'])->middleware('auth')->name('models.batchGenerateStillThumbnails');

// Demo routes
Route::get('/demos', [DemosController::class, 'index'])->name('demos.index');

Route::get('/demos/search-uploaders', [DemosController::class, 'searchUploaders'])->name('demos.search-uploaders');
// Pickers for the demo filter panel. They stay public because the filters do.
Route::get('/demos/search-demo-players', [DemosController::class, 'searchDemoPlayers'])->name('demos.search-demo-players');
Route::get('/demos/search-demo-maps', [DemosController::class, 'searchDemoMaps'])->name('demos.search-demo-maps');
Route::get('/demos/{demo}/download', [DemosController::class, 'download'])->name('demos.download');

// Demo upload routes (requires authentication)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::match(['get', 'post'], '/demos/status', [DemosController::class, 'status'])->name('demos.status');
    Route::post('/demos/{demo}/reprocess', [DemosController::class, 'reprocess'])->name('demos.reprocess');
    Route::delete('/demos/{demo}', [DemosController::class, 'destroy'])->name('demos.destroy');

    Route::post('/demos/start-processing', [DemosController::class, 'startProcessing'])->name('demos.startProcessing');
    Route::post('/demos/reprocess-all-failed', [DemosController::class, 'reprocessAllFailed'])->name('demos.reprocessAllFailed');

    // Manual assignment routes
    Route::get('/demos/maps', [DemosController::class, 'getMaps'])->name('demos.maps');
    Route::get('/demos/maps/{mapname}/records', [DemosController::class, 'getRecords'])->name('demos.records');
    Route::post('/demos/{demo}/assign', [DemosController::class, 'assign'])->name('demos.assign');
    Route::post('/demos/{demo}/unassign', [DemosController::class, 'unassign'])->name('demos.unassign');
    // Attributing a demo to an account, for the freestyle and trick demos that
    // sit on no record. Staff only, enforced in the controller.
    Route::get('/demos/search-players', [DemosController::class, 'searchPlayers'])->name('demos.search-players');
    Route::post('/demos/{demo}/assign-user', [DemosController::class, 'assignToUser'])->name('demos.assign-user');
    Route::post('/demos/{demo}/link-youtube', [DemosController::class, 'linkYoutube'])->name('demos.link-youtube');

    // OAuth routes
    Route::get('/oauth/discord', [App\Http\Controllers\OAuthController::class, 'redirectToDiscord'])->name('oauth.discord');
    Route::get('/oauth/discord/callback', [App\Http\Controllers\OAuthController::class, 'handleDiscordCallback'])->name('oauth.discord.callback');
    Route::post('/oauth/discord/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectDiscord'])->name('oauth.discord.disconnect');

    Route::get('/oauth/twitch', [App\Http\Controllers\OAuthController::class, 'redirectToTwitch'])->name('oauth.twitch');
    Route::get('/oauth/twitch/callback', [App\Http\Controllers\OAuthController::class, 'handleTwitchCallback'])->name('oauth.twitch.callback');
    Route::post('/oauth/twitch/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectTwitch'])->name('oauth.twitch.disconnect');

    Route::get('/oauth/steam', [App\Http\Controllers\OAuthController::class, 'redirectToSteam'])->name('oauth.steam');
    Route::get('/oauth/steam/callback', [App\Http\Controllers\OAuthController::class, 'handleSteamCallback'])->name('oauth.steam.callback');
    Route::post('/oauth/steam/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectSteam'])->name('oauth.steam.disconnect');

    Route::get('/oauth/twitter', [App\Http\Controllers\OAuthController::class, 'redirectToTwitter'])->name('oauth.twitter');
    Route::get('/oauth/twitter/callback', [App\Http\Controllers\OAuthController::class, 'handleTwitterCallback'])->name('oauth.twitter.callback');
    Route::post('/oauth/twitter/disconnect', [App\Http\Controllers\OAuthController::class, 'disconnectTwitter'])->name('oauth.twitter.disconnect');

    // Alias management routes
    Route::post('/aliases', [AliasController::class, 'store'])->name('aliases.store');
    Route::delete('/aliases/{alias}', [AliasController::class, 'destroy'])->name('aliases.destroy');
    Route::post('/aliases/{alias}/report', [AliasReportController::class, 'store'])->name('aliases.report');

    // Alias suggestion routes
    Route::post('/users/{user}/suggest-alias', [App\Http\Controllers\AliasSuggestionController::class, 'store'])->name('alias-suggestions.store');
    Route::post('/alias-suggestions/{suggestion}/approve', [App\Http\Controllers\AliasSuggestionController::class, 'approve'])->name('alias-suggestions.approve');
    Route::post('/alias-suggestions/{suggestion}/reject', [App\Http\Controllers\AliasSuggestionController::class, 'reject'])->name('alias-suggestions.reject');

    // Demo reporting routes
    Route::post('/demos/{demo}/report', [DemoReportController::class, 'store'])->name('demos.report');

    // Record/demo flag routes. Throttled because flagging a record no longer
    // requires a record count of your own - the controller still refuses a
    // duplicate flag of the same type on the same target, but nothing else
    // caps how many different records one account could work through.
    Route::post('/flags', [\App\Http\Controllers\RecordFlagController::class, 'store'])
        ->middleware('throttle:20,60')
        ->name('flags.store');
});

// Frontend error logging (works for both authenticated and anonymous users)
Route::post('/api/frontend-errors', [\App\Http\Controllers\FrontendErrorController::class, 'store']);

// Make the main upload endpoint publicly reachable so the demos page can accept
// anonymous uploads directly. The front-end posts to route('demos.upload') so
// keeping the same route name preserves UI behavior.
Route::post('/demos/upload', [DemosController::class, 'upload'])->name('demos.upload');

    // Local-only debug routes (outside auth group so you can curl them easily during development)
    if (app()->environment('local')) {
        // Simple GET form for manual testing (safer path to avoid wildcard collisions)
        Route::get('/demos/debug/detect', function () {
            if (!app()->environment('local')) abort(404);
            return <<<'HTML'
    <html><body>
    <h3>Debug Upload Detection</h3>
    <form method="post" enctype="multipart/form-data" action="/demos/debug/detect">
        <input type="file" name="file" />
        <button type="submit">Upload</button>
        <input type="hidden" name="_token" value="" />
    </form>
    <p>Use curl: curl -F "file=@/path/to/demos.zip" -X POST http://localhost/demos/debug/detect</p>
    </body></html>
    HTML;
        });

        // POST route for debug detection. We intentionally allow this to be hit without auth
        // or CSRF in local environment for easy debugging via curl.
        Route::post('/demos/debug/detect', [\App\Http\Controllers\DemosController::class, 'debugDetect'])
            ->name('demos.debugDetect');

        // POST route for debug upload. Allows easy CURL testing of archives in local env.
        Route::post('/demos/debug/upload', [\App\Http\Controllers\DemosController::class, 'debugUpload'])
            ->name('demos.debugUpload');
    }

        // Local helper to inspect session token and headers for debugging CSRF
        Route::get('/_debug/session', function (\Illuminate\Http\Request $request) {
            if (!app()->environment('local')) abort(404);
            return response()->json([
                'session_token' => $request->session()->token(),
                'headers' => $request->headers->all(),
                'cookies' => $request->cookies->all(),
            ]);
        });


Route::get('/link-account', [SettingsController::class, 'linkAccount'])->name('link-account')->middleware(['auth', 'verified']);

Route::post('/settings/socialmedia', [SettingsController::class, 'socialmedia'])->name('settings.socialmedia');
Route::post('/settings/preferences', [SettingsController::class, 'preferences'])->name('settings.preferences');
Route::post('/settings/mdd/generate', [SettingsController::class, 'generate'])->name('settings.mdd.generate');
Route::post('/settings/mdd/verify', [SettingsController::class, 'verify'])->name('settings.mdd.verify');
Route::post('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
Route::post('/settings/background', [SettingsController::class, 'background'])->name('settings.background');
Route::delete('/settings/background', [SettingsController::class, 'deleteBackground'])->name('settings.background.destroy');
Route::post('/settings/map-view-preferences', [SettingsController::class, 'mapViewPreferences'])->name('settings.map-view-preferences');
Route::post('/settings/physics-order', [SettingsController::class, 'physicsOrderPreferences'])->name('settings.physics-order');
Route::post('/settings/profile-layout', [SettingsController::class, 'profileLayout'])->name('settings.profile-layout');
Route::post('/settings/global-profile-preferences', [SettingsController::class, 'globalProfilePreferences'])->name('settings.global-profile-preferences');
Route::post('/settings/effects-intensity', [SettingsController::class, 'effectsIntensity'])->name('settings.effects-intensity');
Route::post('/settings/widget', [SettingsController::class, 'widgetSettings'])->middleware('auth')->name('settings.widget');
Route::post('/settings/mapper-claims', [SettingsController::class, 'mapperClaims'])->middleware('auth')->name('settings.mapper-claims');
Route::get('/settings/mapper-claims', [SettingsController::class, 'getMapperClaims'])->middleware('auth')->name('settings.mapper-claims.get');
Route::post('/settings/mapper-claims/preview', [SettingsController::class, 'previewMapperClaim'])->middleware('auth')->name('settings.mapper-claims.preview');
Route::get('/settings/mapper-claims/{claimId}/maps', [SettingsController::class, 'getClaimMaps'])->middleware('auth')->name('settings.mapper-claims.maps');
Route::post('/settings/mapper-claims/{claimId}/exclusions/toggle', [SettingsController::class, 'toggleClaimExclusion'])->middleware('auth')->name('settings.mapper-claims.exclusions.toggle');
Route::post('/settings/mapper-claims/report', [SettingsController::class, 'reportMapperClaim'])->middleware('auth')->name('settings.mapper-claims.report');


Route::get('/notifications/records', [NotificationsController::class, 'records'])->middleware('auth')->name('notifications.index');
Route::post('/notifications/records', [NotificationsController::class, 'recordsclear'])->middleware('auth')->name('notifications.clear');
Route::post('/notifications/records/mark-unread', [NotificationsController::class, 'recordsMarkAllUnread'])->middleware('auth')->name('notifications.mark.unread');
Route::post('/notifications/records/{id}/toggle', [NotificationsController::class, 'recordsToggle'])->middleware('auth')->name('notifications.toggle');

Route::get('/notifications/system', [NotificationsController::class, 'system'])->middleware('auth')->name('notifications.system.index');
Route::post('/notifications/system', [NotificationsController::class, 'systemclear'])->middleware('auth')->name('notifications.system.clear');
Route::post('/notifications/system/mark-unread', [NotificationsController::class, 'systemMarkAllUnread'])->middleware('auth')->name('notifications.system.mark.unread');
Route::post('/notifications/system/{id}/toggle', [NotificationsController::class, 'systemToggle'])->middleware('auth')->name('notifications.system.toggle');

// Mapper/Creator profile API routes
Route::get('/api/profile/{userId}/mapper/stats', [\App\Http\Controllers\MapperProfileController::class, 'stats'])->name('mapper.stats');
Route::get('/api/profile/{userId}/mapper/maps', [\App\Http\Controllers\MapperProfileController::class, 'maps'])->name('mapper.maps');
Route::get('/api/profile/{userId}/mapper/top-players', [\App\Http\Controllers\MapperProfileController::class, 'topPlayers'])->name('mapper.topPlayers');
Route::get('/api/profile/{userId}/mapper/recent-activity', [\App\Http\Controllers\MapperProfileController::class, 'recentActivity'])->name('mapper.recentActivity');
Route::get('/api/profile/{userId}/mapper/heatmap', [\App\Http\Controllers\MapperProfileController::class, 'heatmap'])->name('mapper.heatmap');
Route::get('/api/profile/{userId}/mapper/highlighted-map', [\App\Http\Controllers\MapperProfileController::class, 'highlightedMap'])->name('mapper.highlightedMap');
Route::get('/api/profile/{userId}/mapper/models', [\App\Http\Controllers\MapperProfileController::class, 'models'])->name('mapper.models');
Route::post('/settings/pinned-models', [\App\Http\Controllers\MapperProfileController::class, 'savePinnedModels'])->middleware('auth')->name('settings.pinned-models');
Route::post('/settings/model-group-order', [\App\Http\Controllers\MapperProfileController::class, 'saveModelGroupOrder'])->middleware('auth')->name('settings.model-group-order');

Route::get('/profile/{userId}/progress-bar', [ProfileController::class, 'progressBar'])->name('profile.progressbar');
Route::get('/api/profile/{mddId}/activity', [ProfileController::class, 'activityData'])->name('profile.activity');
Route::get('/api/profile/{mddId}/record-history', [ProfileController::class, 'recordHistory'])->name('profile.record-history');
Route::get('/api/profile/{mddId}/rating-breakdown/{physics}', [ProfileController::class, 'ratingBreakdown'])->middleware(['auth'])->name('profile.rating-breakdown');
Route::post('/profile/{userId}/about-me', [AboutMeController::class, 'submit'])->middleware(['auth', 'verified'])->name('profile.about-me.submit');
Route::post('/profile/{userId}/about-me/delete', [AboutMeController::class, 'requestDelete'])->middleware(['auth', 'verified'])->name('profile.about-me.delete');
Route::get('/profile/mdd/{userId}', [ProfileController::class, 'mdd'])->name('profile.mdd');
Route::get('/profile/{userId}', [ProfileController::class, 'index'])->name('profile.index');

Route::get('/images/flags/{flag}', [WebController::class, 'flags'])->name('images.flags');
Route::get('/storage/thumbs/{image}', [WebController::class, 'thumbs'])->name('images.thumbs');

// Test Map Viewer API - MUST be before wildcard routes
Route::get('/api/test-map-data', [\App\Http\Controllers\TestMapViewerController::class, 'getMapData']);

// Case-insensitive file serving for all storage files
Route::get('/storage/{path}', [FileController::class, 'serveFile'])->where('path', '.*');

// Case-insensitive file serving for baseq3 files
Route::get('/baseq3/{path}', [FileController::class, 'serveBaseq3File'])->where('path', '.*');

Route::get('/announcements', [ChangelogController::class, 'announcements'])->name('announcements');

// Settings (overrides Jetstream's /user/profile)
Route::middleware(['auth'])->group(function () {
    Route::get('/user/settings', [\App\Http\Controllers\Inertia\UserProfileController::class, 'show'])->name('settings.show');
    Route::redirect('/user/profile', '/user/settings', 301);

    // Per-row browser session revoke. Handle is sha256(session.id) so
    // we never put the real session id (= cookie value) into the HTML.
    Route::delete('/user/browser-sessions/{handle}', [\App\Http\Controllers\BrowserSessionsController::class, 'destroy'])
        ->name('browser-sessions.destroy');

    // Launcher personal access tokens
    Route::get('/user/launcher-tokens', [\App\Http\Controllers\LauncherTokenController::class, 'index'])->name('launcher-tokens.index');
    Route::post('/user/launcher-tokens', [\App\Http\Controllers\LauncherTokenController::class, 'store'])->name('launcher-tokens.store');
    Route::delete('/user/launcher-tokens/{tokenId}', [\App\Http\Controllers\LauncherTokenController::class, 'destroy'])->name('launcher-tokens.destroy');

    // General-purpose personal API access tokens (api:read ability)
    Route::get('/user/api-tokens', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/user/api-tokens', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/user/api-tokens/{tokenId}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    // Starred servers, pinned to the top of the server list
    Route::post('/servers/{server}/favorite', [ServersController::class, 'favorite'])->name('servers.favorite');
    Route::delete('/servers/{server}/favorite', [ServersController::class, 'unfavorite'])->name('servers.unfavorite');

    // Server hosting (SFTP serverdemo credentials)
    Route::get('/server-hosting', [\App\Http\Controllers\ServerHostingController::class, 'index'])->name('server-hosting.index');
    Route::post('/server-hosting/apply', [\App\Http\Controllers\ServerHostingController::class, 'apply'])->name('server-hosting.apply');
    Route::post('/server-hosting/acknowledge-password', [\App\Http\Controllers\ServerHostingController::class, 'acknowledgePassword'])->name('server-hosting.acknowledge-password');
    Route::post('/server-hosting/reset-password', [\App\Http\Controllers\ServerHostingController::class, 'resetPassword'])->name('server-hosting.reset-password');
    Route::post('/server-hosting/add-server', [\App\Http\Controllers\ServerHostingController::class, 'addServer'])->name('server-hosting.add-server');
    Route::post('/server-hosting/request-credential', [\App\Http\Controllers\ServerHostingController::class, 'requestCredential'])->name('server-hosting.request-credential');
});


// Maplist routes
Route::get('/maplists', [App\Http\Controllers\MaplistController::class, 'index'])->name('maplists.index');
Route::get('/maplists/play-later', [App\Http\Controllers\MaplistController::class, 'showPlayLater'])->middleware('auth')->name('maplists.playLater');
Route::get('/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'show'])->where('id', '[0-9]+')->name('maplists.show');
Route::get('/api/maps/{mapId}/suggested-tags', [App\Http\Controllers\MaplistController::class, 'getSuggestedTagsForMap'])->name('maps.suggestedTags');

// Authenticated maplist routes (verified required for create/modify)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/api/maplists/user', [App\Http\Controllers\MaplistController::class, 'getUserMaplists'])->name('maplists.user');
    Route::get('/api/maplists/drafts', [App\Http\Controllers\MaplistController::class, 'getDrafts'])->name('maplists.drafts');
    Route::post('/api/maplists/save-draft', [App\Http\Controllers\MaplistController::class, 'saveDraft'])->name('maplists.saveDraft');
    Route::delete('/api/maplists/draft/{id}', [App\Http\Controllers\MaplistController::class, 'deleteDraft'])->name('maplists.deleteDraft');
    Route::post('/api/maplists/create-with-maps', [App\Http\Controllers\MaplistController::class, 'createWithMaps'])->name('maplists.createWithMaps');
    Route::post('/api/maplists/{id}/reorder', [App\Http\Controllers\MaplistController::class, 'reorderMaps'])->name('maplists.reorder');
    Route::post('/api/maplists/{id}/maps', [App\Http\Controllers\MaplistController::class, 'addMap'])->name('maplists.addMap');
    Route::post('/api/maplists/{id}/like', [App\Http\Controllers\MaplistController::class, 'toggleLike'])->name('maplists.toggleLike');
    Route::post('/api/maplists/{id}/favorite', [App\Http\Controllers\MaplistController::class, 'toggleFavorite'])->name('maplists.toggleFavorite');
    Route::post('/api/maplists', [App\Http\Controllers\MaplistController::class, 'store'])->name('maplists.store');
    Route::put('/api/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'update'])->where('id', '[0-9]+')->name('maplists.update');
    Route::delete('/api/maplists/{id}', [App\Http\Controllers\MaplistController::class, 'destroy'])->where('id', '[0-9]+')->name('maplists.destroy');
    Route::delete('/api/maplists/{maplistId}/maps/{mapId}', [App\Http\Controllers\MaplistController::class, 'removeMap'])->name('maplists.removeMap');
    Route::get('/api/maps/search', [App\Http\Controllers\MaplistController::class, 'searchMaps'])->name('maps.search');

    // Tag routes
    Route::post('/api/maps/{id}/tags', [App\Http\Controllers\TagController::class, 'addToMap'])->name('tags.addToMap');
    Route::delete('/api/maps/{mapId}/tags/{tagId}', [App\Http\Controllers\TagController::class, 'removeFromMap'])->name('tags.removeFromMap');
    Route::post('/api/maplists/{id}/tags', [App\Http\Controllers\TagController::class, 'addToMaplist'])->name('tags.addToMaplist');
    Route::delete('/api/maplists/{maplistId}/tags/{tagId}', [App\Http\Controllers\TagController::class, 'removeFromMaplist'])->name('tags.removeFromMaplist');

    // Saved map filter routes (per-user, private)
    Route::get('/api/saved-map-filters', [App\Http\Controllers\SavedMapFilterController::class, 'index'])->name('savedMapFilters.index');
    Route::post('/api/saved-map-filters', [App\Http\Controllers\SavedMapFilterController::class, 'store'])->name('savedMapFilters.store');
    Route::delete('/api/saved-map-filters/{savedMapFilter}', [App\Http\Controllers\SavedMapFilterController::class, 'destroy'])->name('savedMapFilters.destroy');
});

// Public tag routes
Route::get('/api/tags', [App\Http\Controllers\TagController::class, 'index'])->name('tags.index');

// Map filter profiles (lazy-loaded)
Route::get('/api/maps/profiles', [MapsController::class, 'profiles'])->name('maps.profiles');

// Donation routes
Route::get('/donations', [DonationController::class, 'index'])->name('donations.index');
Route::get('/api/donations/progress', [DonationController::class, 'getProgress'])->name('donations.progress');

// Roadmap route
Route::get('/roadmap', [WebController::class, 'roadmap'])->name('roadmap');

// Admin tools
Route::get('/admin/models-audit', [App\Http\Controllers\ModelsAuditController::class, 'index'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit');

Route::get('/admin/models-audit/download', [App\Http\Controllers\ModelsAuditController::class, 'download'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.download');

Route::post('/admin/models-audit/compare', [App\Http\Controllers\ModelsAuditController::class, 'compare'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.compare');

Route::post('/admin/models-audit/save-description', [App\Http\Controllers\ModelsAuditController::class, 'saveDescription'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.save-description');

Route::post('/admin/models-audit/build-extras-zip', [App\Http\Controllers\ModelsAuditController::class, 'buildExtrasZip'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.build-extras-zip');

Route::post('/admin/models-audit/mark-manual-review', [App\Http\Controllers\ModelsAuditController::class, 'markManualReview'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.mark-manual-review');

Route::post('/admin/models-audit/mark-failed-manual', [App\Http\Controllers\ModelsAuditController::class, 'markFailedManual'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.mark-failed-manual');

Route::get('/admin/models-audit/cached-files', [App\Http\Controllers\ModelsAuditController::class, 'cachedFiles'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.cached-files');

Route::post('/admin/models-audit/validate-local-files', [App\Http\Controllers\ModelsAuditController::class, 'validateLocalFiles'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.validate-local-files');

Route::post('/admin/models-audit/resolve', [App\Http\Controllers\ModelsAuditController::class, 'resolve'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.resolve');

Route::post('/admin/models-audit/import', [App\Http\Controllers\ModelsAuditController::class, 'importModel'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.import');

Route::post('/admin/models-audit/import-pk3', [App\Http\Controllers\ModelsAuditController::class, 'importPk3'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.import-pk3');

Route::get('/admin/models-audit/ws-detail-check', [App\Http\Controllers\ModelsAuditController::class, 'wsDetailCheck'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.ws-detail-check');

Route::get('/admin/models-audit/missing-skins-in-name', [App\Http\Controllers\ModelsAuditController::class, 'missingSkinsInName'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.missing-skins-in-name');

Route::post('/admin/models-audit/fix-model-name', [App\Http\Controllers\ModelsAuditController::class, 'fixModelName'])
    ->middleware(['auth', App\Http\Middleware\AdminAccessMiddleware::class])
    ->name('admin.models-audit.fix-model-name');

// PayPal webhook (no CSRF protection needed for webhooks)
Route::post('/api/paypal/webhook', [\App\Http\Controllers\PayPalWebhookController::class, 'handleWebhook'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// DefragHQ Admin - Donation Management (requires authentication)
// Note: All donation management is now handled by Filament admin panel
// Route::middleware('auth')->prefix('defraghq')->group(function () {
//     Route::post('/donations', [DonationManagementController::class, 'storeDonation'])->name('defraghq.donations.store');
//     Route::put('/donations/{donation}', [DonationManagementController::class, 'updateDonation'])->name('defraghq.donations.update');
//     Route::delete('/donations/{donation}', [DonationManagementController::class, 'deleteDonation'])->name('defraghq.donations.delete');
//
//     Route::post('/self-raised', [DonationManagementController::class, 'storeSelfRaised'])->name('defraghq.selfraised.store');
//     Route::put('/self-raised/{selfRaised}', [DonationManagementController::class, 'updateSelfRaised'])->name('defraghq.selfraised.update');
//     Route::delete('/self-raised/{selfRaised}', [DonationManagementController::class, 'deleteSelfRaised'])->name('defraghq.selfraised.delete');
//
//     Route::post('/donation-goal', [DonationManagementController::class, 'updateGoal'])->name('defraghq.goal.update');
// });

// Forum Archive
Route::prefix('forum-archive')->group(function () {
    Route::get('/', [App\Http\Controllers\ForumArchiveController::class, 'index'])->name('forum.archive.index');
    Route::get('/topic/{topicId}', [App\Http\Controllers\ForumArchiveController::class, 'show'])->name('forum.archive.show');
});

// Wiki
Route::prefix('wiki')->group(function () {
    Route::get('/', [App\Http\Controllers\WikiController::class, 'index'])->name('wiki.index');
    Route::get('/search', [App\Http\Controllers\WikiController::class, 'search'])->name('wiki.search');
    Route::get('/search-index', [App\Http\Controllers\WikiController::class, 'searchIndex'])->name('wiki.searchIndex');
    Route::get('/changes', [App\Http\Controllers\WikiController::class, 'globalHistory'])->name('wiki.globalHistory');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/create', [App\Http\Controllers\WikiController::class, 'create'])->name('wiki.create');
        Route::post('/', [App\Http\Controllers\WikiController::class, 'store'])->name('wiki.store');
        Route::post('/ban', [App\Http\Controllers\WikiController::class, 'ban'])->name('wiki.ban');
        Route::post('/unban', [App\Http\Controllers\WikiController::class, 'unban'])->name('wiki.unban');
        Route::post('/reorder', [App\Http\Controllers\WikiController::class, 'reorder'])->name('wiki.reorder');
        Route::post('/upload-image', [App\Http\Controllers\WikiController::class, 'uploadImage'])->name('wiki.uploadImage');
    });

    Route::get('/{slug}', [App\Http\Controllers\WikiController::class, 'show'])->name('wiki.show');
    Route::get('/{slug}/history', [App\Http\Controllers\WikiController::class, 'history'])->name('wiki.history');
    Route::get('/{slug}/revision/{revision}', [App\Http\Controllers\WikiController::class, 'revision'])->name('wiki.revision');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/{slug}/edit', [App\Http\Controllers\WikiController::class, 'edit'])->name('wiki.edit');
        Route::put('/{slug}', [App\Http\Controllers\WikiController::class, 'update'])->name('wiki.update');
        Route::post('/{slug}/revert/{revision}', [App\Http\Controllers\WikiController::class, 'revert'])->name('wiki.revert');
        Route::delete('/{slug}/revision/{revision}', [App\Http\Controllers\WikiController::class, 'deleteRevision'])->name('wiki.deleteRevision');
        Route::post('/{slug}/toggle-lock', [App\Http\Controllers\WikiController::class, 'toggleLock'])->name('wiki.toggleLock');
        Route::delete('/{slug}', [App\Http\Controllers\WikiController::class, 'destroy'])->name('wiki.destroy');
    });
});

// Clean URLs for admin-created pages (catch-all, must be last route)
// Exclude prefixes used by other route files (clans, headhunter, marketplace, tournaments)
Route::get("/{slug}", [App\Http\Controllers\PagesController::class, "index"])->name("pages.show.clean")->where("slug", "(?!clans|headhunter|marketplace|tournaments|wiki)[a-z0-9\\-]+");
