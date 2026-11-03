<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
 use App\Http\Controllers\ServerController;
Route::get('/servidor', [ServerController::class, 'index']);