<?php

use App\Http\Controllers\EntityController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// "/" and other entity paths are served by kusikusicms/routes (App\Http\Controllers\WebsiteController).

// Sandbox only: passwordless login for testing.
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.store');

Route::middleware(EnsureAdmin::class)->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/entities', [HomeController::class, 'index'])->name('home');
    Route::get('/entity/{id}', [EntityController::class, 'show'])->name('entity.show');
    Route::post('/entity', [EntityController::class, 'store'])->name('entity.store');
    Route::patch('/entity/{id}', [EntityController::class, 'update'])->name('entity.update');
    Route::delete('/entity/{id}', [EntityController::class, 'destroy'])->name('entity.destroy');
});
