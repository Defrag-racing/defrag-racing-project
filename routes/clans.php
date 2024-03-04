<?php

use App\Http\Controllers\Clans\ClansController;

Route::get('/', [ClansController::class, 'index'])->name('clans.index');
