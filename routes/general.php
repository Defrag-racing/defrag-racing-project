<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\MapDownloadController;
use App\Http\Controllers\DemoDownloadController;
use App\Http\Controllers\ResultsDownloadController;

Route::get('/maps/download/{mapname}', [WebController::class, 'map'])->name('maps.download');

Route::get('/images/flags/{flag}', [WebController::class, 'flags'])->name('images.flags');
Route::get('/storage/thumbs/{image}', [WebController::class, 'thumbs'])->name('images.thumbs');

Route::get('/tournaments/{tournament}/{round}/results/download', [ResultsDownloadController::class, 'results'])->name('tournaments.results.download');
Route::get('/tournaments/{tournament}/{round}/results/download/anonymous', [ResultsDownloadController::class, 'anonymous'])->name('tournaments.results.anonymous.download');

Route::get('/tournaments/demos/{demo}/storage/download', [DemoDownloadController::class, 'download'])->name('tournaments.demos.download');
Route::get('/tournaments/maps/{map}/storage/download', [MapDownloadController::class, 'download'])->name('tournaments.maps.download');