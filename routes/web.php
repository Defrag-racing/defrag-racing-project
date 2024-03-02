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
use App\Http\Controllers\TournamentsController;
use App\Http\Controllers\TournamentManagementController;
use App\Http\Controllers\Tournaments\DonationController;
use App\Http\Controllers\Tournaments\FaqController;
use App\Http\Controllers\Tournaments\SuggestionController;

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

Route::get('/tournaments', [TournamentsController::class, 'index'])->name('tournaments.index');
Route::get('/tournaments/{tournament}', [TournamentsController::class, 'show'])->name('tournaments.show');
Route::get('/tournaments/{tournament}/rules', [TournamentsController::class, 'rules'])->name('tournaments.rules');
Route::get('/tournaments/{tournament}/donations', [TournamentsController::class, 'donations'])->name('tournaments.donations');
Route::get('/tournaments/{tournament}/faqs', [TournamentsController::class, 'faqs'])->name('tournaments.faqs');

Route::get('/tournaments/manage/tournament/create', [TournamentManagementController::class, 'create'])->name('tournaments.create');
Route::post('/tournaments/manage/tournament/create', [TournamentManagementController::class, 'store'])->name('tournaments.store');

Route::post('/tournaments/{tournament}/suggestions/create', [SuggestionController::class, 'store'])->name('tournaments.suggestions.store');

Route::middleware(['tournaments.management'])->prefix('/tournaments/manage')->group(function () {
    Route::get('{tournament}', [TournamentManagementController::class, 'manage'])->name('tournaments.manage');

    Route::get('{tournament}/suggestions', [SuggestionController::class, 'index'])->name('tournaments.suggestions.index');
    Route::get('{tournament}/suggestions/{suggestion}/delete', [SuggestionController::class, 'destroy'])->name('tournaments.suggestions.destroy');

    Route::get('{tournament}/edit', [TournamentManagementController::class, 'edit'])->name('tournaments.edit');
    Route::post('{tournament}/edit', [TournamentManagementController::class, 'update'])->name('tournaments.update');
    
    Route::get('{tournament}/donations', [DonationController::class, 'index'])->name('tournaments.donations.manage');
    Route::get('{tournament}/donations/create', [DonationController::class, 'create'])->name('tournaments.donations.create');
    Route::post('{tournament}/donations/store', [DonationController::class, 'store'])->name('tournaments.donations.store');
    Route::get('{tournament}/donations/{donation}/edit', [DonationController::class, 'edit'])->name('tournaments.donations.edit');
    Route::post('{tournament}/donations/{donation}/update', [DonationController::class, 'update'])->name('tournaments.donations.update');
    Route::get('{tournament}/donations/{donation}', [DonationController::class, 'destroy'])->name('tournaments.donations.destroy');


    Route::get('{tournament}/faqs', [FaqController::class, 'index'])->name('tournaments.faqs.manage');
    Route::get('{tournament}/faqs/create', [FaqController::class, 'create'])->name('tournaments.faqs.create');
    Route::post('{tournament}/faqs/store', [FaqController::class, 'store'])->name('tournaments.faqs.store');
    Route::get('{tournament}/faqs/{faq}/edit', [FaqController::class, 'edit'])->name('tournaments.faqs.edit');
    Route::post('{tournament}/faqs/{faq}/update', [FaqController::class, 'update'])->name('tournaments.faqs.update');
    Route::get('{tournament}/faqs/{faq}', [FaqController::class, 'destroy'])->name('tournaments.faqs.destroy');
});