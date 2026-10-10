<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\EntityController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/entity/{id}', [EntityController::class, 'show', ])->name('entity.show');
Route::post('/entity', [EntityController::class, 'store'])->name('entity.store');
