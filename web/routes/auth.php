<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

require __DIR__.'/settings.php';
