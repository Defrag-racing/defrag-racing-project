<?php

use App\Http\Controllers\Clans\ClansController;
use App\Http\Controllers\Clans\ManageClanController;

Route::get('/', [ClansController::class, 'index'])->name('clans.index');
Route::get('/create', [ClansController::class, 'create'])->name('clans.create');
Route::post('/create', [ClansController::class, 'store'])->name('clans.store');

Route::prefix('manage')->group(function () {
    Route::post('/invite', [ManageClanController::class, 'invite'])->name('clans.manage.invite');
    Route::post('/kick', [ManageClanController::class, 'kick'])->name('clans.manage.kick');
    Route::post('/leave', [ManageClanController::class, 'leave'])->name('clans.manage.leave');
});
