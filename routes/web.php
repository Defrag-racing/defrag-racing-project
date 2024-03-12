<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\MapsController;
use App\Http\Controllers\BundlesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\EndpointController;
use App\Http\Controllers\ProfileController;

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
Route::post('/search', [SearchController::class, 'search'])->name('search');

Route::get('/servers', [ServersController::class, 'index'])->name('servers');
Route::get('/servers/json', [EndpointController::class, 'index'])->name('servers.json');

Route::get('/maps', [MapsController::class, 'index'])->name('maps');
Route::get('/maps/filters', [MapsController::class, 'filters'])->name('maps.filters');
Route::get('/maps/download/{mapname}', [WebController::class, 'map'])->name('maps.download');

Route::get('/maps/{mapname}', [MapsController::class, 'map'])->name('maps.map');

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking');

Route::get('/records', [RecordsController::class, 'index'])->name('records');

Route::get('/bundles/{id?}/{slug?}', [BundlesController::class, 'index'])->name('bundles');


Route::post('/settings/socialmedia', [SettingsController::class, 'socialmedia'])->name('settings.socialmedia');
Route::post('/settings/preferences', [SettingsController::class, 'preferences'])->name('settings.preferences');
Route::post('/settings/mdd/generate', [SettingsController::class, 'generate'])->name('settings.mdd.generate');
Route::post('/settings/mdd/verify', [SettingsController::class, 'verify'])->name('settings.mdd.verify');


Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
Route::post('/notifications', [NotificationsController::class, 'clear'])->name('notifications.clear');

Route::get('/profile/{userId}', [ProfileController::class, 'index'])->name('profile.index');
Route::get('/profile/mdd/{userId}', [ProfileController::class, 'mdd'])->name('profile.mdd');

Route::get('/images/flags/{flag}', [WebController::class, 'flags'])->name('images.flags');
Route::get('/storage/thumbs/{image}', [WebController::class, 'thumbs'])->name('images.thumbs');


Route::get('/test', function () {
    // $users = \App\Models\User::all();

    // $count = 50;

    // // make 50 teams with random cpm_player_id and vq3_player_id

    // for ($i = 0; $i < $count; $i++) {
    //     $team = new \App\Models\Team();
    //     $team->name = 'Team ' . $i;

    //     $team->cpm_player_id = $users->random()->id;
    //     $team->vq3_player_id = $users->random()->id;
    //     $team->save();
    // }
});