<?php

use App\Http\Controllers\Clans\ClansController;
use App\Http\Controllers\Clans\ManageClanController;

Route::get('/', [ClansController::class, 'index'])->name('clans.index');
Route::get('/{clan}', [ClansController::class, 'show'])->name('clans.show');

Route::prefix('manage')->group(function () {
    Route::get('/create', [ManageClanController::class, 'create'])->name('clans.manage.create');
    Route::post('/create', [ManageClanController::class, 'store'])->name('clans.manage.store');

    Route::post('/edit/{clan}', [ManageClanController::class, 'update'])->name('clans.manage.update');

    Route::post('/invite', [ManageClanController::class, 'invite'])->name('clans.manage.invite');
    Route::post('/kick', [ManageClanController::class, 'kick'])->name('clans.manage.kick');
    Route::post('/leave', [ManageClanController::class, 'leave'])->name('clans.manage.leave');
    Route::post('/transfer', [ManageClanController::class, 'transfer'])->name('clans.manage.transfer');
    Route::post('/dismantle', [ManageClanController::class, 'dismantle'])->name('clans.manage.dismantle');
});
