<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\MapsController;
use App\Http\Controllers\BundlesController;
use App\Http\Controllers\SearchController;

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
    function django_hash_password($password, $salt, $iterations = 260000)
    {
        // Use PBKDF2 with SHA-256
        $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

        // Format the hash for Django storage
        $encoded_hash = base64_encode($hash);
        return "pbkdf2_sha256$" . $iterations . "$" . $salt . "$" . $encoded_hash;
    }

    function django_verify_password($password, $old_hash)
    {
        list($algorithm, $iterations, $salt, $encoded_hash) = explode('$', $old_hash);

        $hash = base64_decode($encoded_hash, true);

        $hashed_password = django_hash_password($password, $salt, (int)$iterations);

        return hash_equals($hashed_password, $old_hash);
    }

    $oldhash = 'pbkdf2_sha256$260000$c9RUHdPA0H1CtOpyXQlAvu$nzN/Re8CDjJakh572tqKcukfItCjEXks0jwLeYBAJUI=';

    return django_verify_password('eoltest', $oldhash) ? 'True' : 'False';
});

Route::get('/', [WebController::class, 'home'])->name('home');
Route::post('/search', [SearchController::class, 'search'])->name('search');

Route::get('/servers', [WebController::class, 'servers'])->name('servers');

Route::get('/maps', [MapsController::class, 'index'])->name('maps');
Route::get('/maps/{mapname}', [MapsController::class, 'map'])->name('maps.map');

Route::get('/bundles', [BundlesController::class, 'index'])->name('bundles');
