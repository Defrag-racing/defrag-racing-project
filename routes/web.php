<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\MapsController;
use App\Http\Controllers\BundlesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\RecordsController;

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

Route::get('/test', function () {
    $scrapper = new \App\External\Q3DFScrapper();

    return $scrapper->scrape();
});

Route::get('/', [WebController::class, 'home'])->name('home');
Route::post('/search', [SearchController::class, 'search'])->name('search');

Route::get('/servers', [ServersController::class, 'index'])->name('servers');

Route::get('/maps', [MapsController::class, 'index'])->name('maps');
Route::get('/maps/{mapname}', [MapsController::class, 'map'])->name('maps.map');

Route::get('/records', [RecordsController::class, 'index'])->name('records');

Route::get('/bundles', [BundlesController::class, 'index'])->name('bundles');
