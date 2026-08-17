<?php

use App\Http\Controllers\InstagramCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/instagram/callback', InstagramCallbackController::class)
    ->name('instagram.callback');
