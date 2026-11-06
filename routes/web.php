<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
 use App\Http\Controllers\ServerController;
use App\Http\Controllers\LoginController;
use App\Http\Middleware\SecurityLoginGuard;

Route::get('/login', [LoginController::class, 'show'])->middleware('guest')->name('login');
// SecurityLoginGuard bloquea la IP tras 5 fallos en 5 minutos
Route::post('/login', [LoginController::class, 'login'])->middleware(['guest', SecurityLoginGuard::class]);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/servidor', [ServerController::class, 'index'])
    ->middleware(['auth', 'can:view-security']);
// Alias: /dashboard lleva al panel (la protección la aplica /servidor)
Route::redirect('/dashboard', '/servidor');