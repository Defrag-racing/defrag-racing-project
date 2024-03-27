<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

Route::get('/maps/download/{mapname}', [WebController::class, 'map'])->name('maps.download');