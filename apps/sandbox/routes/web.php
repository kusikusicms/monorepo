<?php

use App\Http\Controllers\EntityController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/entity/{id}', [EntityController::class, 'show'])->name('entity.show');
Route::post('/entity', [EntityController::class, 'store'])->name('entity.store');
