<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

Route::get('/maps/download/{mapname}', [WebController::class, 'map'])->name('maps.download');

Route::get('/images/flags/{flag}', [WebController::class, 'flags'])->name('images.flags');
Route::get('/storage/thumbs/{image}', [WebController::class, 'thumbs'])->name('images.thumbs');