<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web SPA Catch-All Route
|--------------------------------------------------------------------------
| All web routes outside /api, /sanctum, and /up are handled by the Vue SPA.
|
| The named `login` route gives Laravel's authentication middleware a
| redirect target for unauthenticated browser requests (the SPA renders
| the login screen there). JSON API clients still receive a 401 payload.
*/
Route::get('/login', function () {
    return view('app');
})->name('login');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|up).*$');
