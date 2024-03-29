<?php

use App\Http\Controllers\TournamentsController;
use App\Http\Controllers\TournamentManagementController;
use App\Http\Controllers\Tournaments\DonationController;
use App\Http\Controllers\Tournaments\FaqController;
use App\Http\Controllers\Tournaments\RoundManagementController;
use App\Http\Controllers\Tournaments\RoundController;
use App\Http\Controllers\Tournaments\SuggestionController;
use App\Http\Controllers\Tournaments\StreamerController;
use App\Http\Controllers\Tournaments\RelatedTournamentController;
use App\Http\Controllers\Tournaments\TeamController;
use App\Http\Controllers\Tournaments\RoundMapController;
use App\Http\Controllers\Tournaments\NewsController;
use App\Http\Controllers\Tournaments\NewsManagementController;
use App\Http\Controllers\Tournaments\OrganizersManagementController;
use App\Http\Controllers\Tournaments\ValidateDemosController;
use App\Http\Controllers\DemoDownloadController;

Route::get('/tournaments/demos/{demo}/storage/download', [DemoDownloadController::class, 'download'])->name('tournaments.demos.download');

Route::get('/tournaments', [TournamentsController::class, 'index'])->name('tournaments.index');
Route::get('/tournaments/{tournament}', [TournamentsController::class, 'show'])->name('tournaments.show');
Route::get('/tournaments/{tournament}/rules', [TournamentsController::class, 'rules'])->name('tournaments.rules');
Route::get('/tournaments/{tournament}/donations', [TournamentsController::class, 'donations'])->name('tournaments.donations');
Route::get('/tournaments/{tournament}/faqs', [TournamentsController::class, 'faqs'])->name('tournaments.faqs');

Route::prefix('/tournaments/{tournament}/rounds')->group(function () {
    Route::get('/', [RoundController::class, 'index'])->name('tournaments.rounds.index');
    Route::post('/{round}/comment', [RoundController::class, 'comment'])->name('tournaments.rounds.comment');

    Route::post('/{round}/submit', [RoundController::class, 'submit'])->name('tournaments.rounds.submit');
});

Route::get('/tournaments/{tournament}/news', [NewsController::class, 'index'])->name('tournaments.news.index');
Route::post('/tournaments/{tournament}/news/{new}/comment', [NewsController::class, 'comment'])->name('tournaments.news.comment');

Route::post('/tournaments/{tournament}/comments/{comment}/reply', [NewsController::class, 'reply'])->name('tournaments.comments.reply');

Route::prefix('/tournaments/{tournament}/teams')->group(function () {
    Route::get('/index', [TeamController::class, 'index'])->name('tournaments.teams.index');
    Route::get('/manage', [TeamController::class, 'manage'])->name('tournaments.teams.manage');
    Route::get('/create', [TeamController::class, 'create'])->name('tournaments.teams.create');
    Route::post('/create', [TeamController::class, 'store'])->name('tournaments.teams.store');
    Route::get('/leave', [TeamController::class, 'leave'])->name('tournaments.teams.leave');
    Route::post('/invite', [TeamController::class, 'invite'])->name('tournaments.teams.invite');

    Route::post('/{invitation}/accept', [TeamController::class, 'accept'])->name('tournaments.teams.accept');
    Route::post('/{invitation}/reject', [TeamController::class, 'reject'])->name('tournaments.teams.reject');
});

Route::get('/tournaments/manage/tournament/create', [TournamentManagementController::class, 'create'])->name('tournaments.create');
Route::post('/tournaments/manage/tournament/create', [TournamentManagementController::class, 'store'])->name('tournaments.store');

Route::post('/tournaments/{tournament}/suggestions/create', [SuggestionController::class, 'store'])->name('tournaments.suggestions.store');

Route::middleware(['tournaments.management'])->prefix('/tournaments/manage/{tournament}')->group(function () {
    Route::get('/', [TournamentManagementController::class, 'manage'])->name('tournaments.manage');

    Route::get('/suggestions', [SuggestionController::class, 'index'])->name('tournaments.suggestions.index');
    Route::get('/suggestions/{suggestion}/delete', [SuggestionController::class, 'destroy'])->name('tournaments.suggestions.destroy');

    Route::get('/edit', [TournamentManagementController::class, 'edit'])->name('tournaments.edit');
    Route::post('/edit', [TournamentManagementController::class, 'update'])->name('tournaments.update');
    
    Route::get('/donations', [DonationController::class, 'index'])->name('tournaments.donations.manage');
    Route::get('/donations/create', [DonationController::class, 'create'])->name('tournaments.donations.create');
    Route::post('/donations/store', [DonationController::class, 'store'])->name('tournaments.donations.store');
    Route::get('/donations/{donation}/edit', [DonationController::class, 'edit'])->name('tournaments.donations.edit');
    Route::post('/donations/{donation}/update', [DonationController::class, 'update'])->name('tournaments.donations.update');
    Route::get('/donations/{donation}', [DonationController::class, 'destroy'])->name('tournaments.donations.destroy');


    Route::get('/faqs', [FaqController::class, 'index'])->name('tournaments.faqs.manage');
    Route::get('/faqs/create', [FaqController::class, 'create'])->name('tournaments.faqs.create');
    Route::post('/faqs/store', [FaqController::class, 'store'])->name('tournaments.faqs.store');
    Route::get('/faqs/{faq}/edit', [FaqController::class, 'edit'])->name('tournaments.faqs.edit');
    Route::post('/faqs/{faq}/update', [FaqController::class, 'update'])->name('tournaments.faqs.update');
    Route::get('/faqs/{faq}', [FaqController::class, 'destroy'])->name('tournaments.faqs.destroy');

    
    Route::get('/streamers', [StreamerController::class, 'index'])->name('tournaments.streamers.manage');
    Route::get('/streamers/create', [StreamerController::class, 'create'])->name('tournaments.streamers.create');
    Route::post('/streamers/store', [StreamerController::class, 'store'])->name('tournaments.streamers.store');
    Route::get('/streamers/{streamer}/edit', [StreamerController::class, 'edit'])->name('tournaments.streamers.edit');
    Route::post('/streamers/{streamer}/update', [StreamerController::class, 'update'])->name('tournaments.streamers.update');
    Route::get('/streamers/{streamer}', [StreamerController::class, 'destroy'])->name('tournaments.streamers.destroy');

    
    Route::get('/related', [RelatedTournamentController::class, 'index'])->name('tournaments.related.manage');
    Route::get('/related/create', [RelatedTournamentController::class, 'create'])->name('tournaments.related.create');
    Route::post('/related/store', [RelatedTournamentController::class, 'store'])->name('tournaments.related.store');
    Route::get('/related/{relatedTournament}/edit', [RelatedTournamentController::class, 'edit'])->name('tournaments.related.edit');
    Route::post('/related/{relatedTournament}/update', [RelatedTournamentController::class, 'update'])->name('tournaments.related.update');
    Route::get('/related/{relatedTournament}', [RelatedTournamentController::class, 'destroy'])->name('tournaments.related.destroy');


    Route::get('/rounds', [RoundManagementController::class, 'index'])->name('tournaments.rounds.manage');
    Route::get('/rounds/create', [RoundManagementController::class, 'create'])->name('tournaments.rounds.create');
    Route::post('/rounds/store', [RoundManagementController::class, 'store'])->name('tournaments.rounds.store');
    Route::get('/rounds/{round}/edit', [RoundManagementController::class, 'edit'])->name('tournaments.rounds.edit');
    Route::post('/rounds/{round}/update', [RoundManagementController::class, 'update'])->name('tournaments.rounds.update');
    Route::post('/rounds/{round}', [RoundManagementController::class, 'destroy'])->name('tournaments.rounds.destroy');

    Route::get('/rounds/{round}/maps', [RoundMapController::class, 'index'])->name('tournaments.rounds.maps.index');
    Route::get('/rounds/{round}/maps/create', [RoundMapController::class, 'create'])->name('tournaments.rounds.maps.create');
    Route::post('/rounds/{round}/maps/create', [RoundMapController::class, 'store'])->name('tournaments.rounds.maps.store');
    Route::post('/rounds/{round}/maps/existing/create', [RoundMapController::class, 'existingstore'])->name('tournaments.rounds.maps.existing.store');
    Route::get('/rounds/{round}/maps/{map}/done', [RoundMapController::class, 'destroy'])->name('tournaments.rounds.maps.destroy');

    Route::get('/news', [NewsManagementController::class, 'index'])->name('tournaments.news.manage');
    Route::get('/news/create', [NewsManagementController::class, 'create'])->name('tournaments.news.create');
    Route::post('/news/store', [NewsManagementController::class, 'store'])->name('tournaments.news.store');
    Route::get('/news/{news}/edit', [NewsManagementController::class, 'edit'])->name('tournaments.news.edit');
    Route::post('/news/{news}/update', [NewsManagementController::class, 'update'])->name('tournaments.news.update');
    Route::get('/news/{news}', [NewsManagementController::class, 'destroy'])->name('tournaments.news.destroy');

    Route::get('/organizers', [OrganizersManagementController::class, 'index'])->name('tournaments.organizers.manage');
    Route::get('/organizers/create', [OrganizersManagementController::class, 'create'])->name('tournaments.organizers.create');
    Route::post('/organizers/store', [OrganizersManagementController::class, 'store'])->name('tournaments.organizers.store');
    Route::get('/organizers/{organizer}/edit', [OrganizersManagementController::class, 'edit'])->name('tournaments.organizers.edit');
    Route::post('/organizers/{organizer}/update', [OrganizersManagementController::class, 'update'])->name('tournaments.organizers.update');
    Route::get('/organizers/{organizer}', [OrganizersManagementController::class, 'destroy'])->name('tournaments.organizers.destroy');
});

Route::middleware(['tournaments.validation'])->prefix('/tournaments/manage/{tournament}/validation')->group(function () {
    Route::get('/', [ValidateDemosController::class, 'index'])->name('tournaments.validation.index');
    Route::get('/{round}', [ValidateDemosController::class, 'round'])->name('tournaments.validation.round');
    Route::post('/{round}/demos/{demo}/approve', [ValidateDemosController::class, 'approve'])->name('tournaments.validation.approve');
    Route::post('/{round}/demos/{demo}/reject', [ValidateDemosController::class, 'reject'])->name('tournaments.validation.reject');
});