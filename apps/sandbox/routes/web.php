<?php

use App\Http\Controllers\EntityController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// "/" and other entity paths are served by kusikusicms/routes (App\Http\Controllers\WebsiteController).
Route::get('/entities', [HomeController::class, 'index'])->name('home');
Route::get('/entity/{id}', [EntityController::class, 'show'])->name('entity.show');
Route::post('/entity', [EntityController::class, 'store'])->name('entity.store');
Route::patch('/entity/{id}', [EntityController::class, 'update'])->name('entity.update');
Route::delete('/entity/{id}', [EntityController::class, 'destroy'])->name('entity.destroy');
