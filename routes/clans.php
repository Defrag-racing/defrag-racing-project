<?php

use App\Http\Controllers\Clans\ClansController;
use App\Http\Controllers\Clans\ManageClanController;

Route::get('/', [ClansController::class, 'index'])->name('clans.index');
Route::get('/create', [ClansController::class, 'create'])->name('clans.create');
Route::post('/create', [ClansController::class, 'store'])->name('clans.store');

Route::prefix('manage')->group(function () {
    Route::get('/', [ManageClanController::class, 'index'])->name('clans.manage.index');
});
