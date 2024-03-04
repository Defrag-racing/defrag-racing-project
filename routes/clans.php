<?php

use App\Http\Controllers\Clans\ClansController;

Route::get('/', [ClansController::class, 'index'])->name('clans.index');
Route::get('/create', [ClansController::class, 'create'])->name('clans.create');
Route::post('/create', [ClansController::class, 'store'])->name('clans.store');
