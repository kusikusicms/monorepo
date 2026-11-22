<?php

use Illuminate\Support\Facades\Route;
use KusikusiCMS\Api\Api;
use KusikusiCMS\Api\Http\Controllers\AuthController;
use KusikusiCMS\Api\Http\Controllers\CapabilitiesController;
use KusikusiCMS\Api\Http\Controllers\EntityContentsController;
use KusikusiCMS\Api\Http\Controllers\EntityController;
use KusikusiCMS\Api\Http\Controllers\EntityRelationsController;
use KusikusiCMS\Api\Http\Controllers\EntityTreeController;
use KusikusiCMS\Api\Http\Controllers\MeController;
use KusikusiCMS\Api\Http\Controllers\SessionController;

Route::get('capabilities', CapabilitiesController::class)->middleware('throttle:60,1')->name('capabilities');

Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

if (Api::spaEnabled()) {
    Route::post('auth/session', [SessionController::class, 'store'])->name('auth.session');
}

Route::middleware((array) config('kusikusicms.api.auth_middleware'))->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [MeController::class, 'show'])->name('me');

    Route::get('entities', [EntityController::class, 'index'])->name('entities.index');
    Route::post('entities', [EntityController::class, 'store'])->name('entities.store');
    Route::get('entities/{entity}', [EntityController::class, 'show'])->name('entities.show');
    Route::patch('entities/{entity}', [EntityController::class, 'update'])->name('entities.update');
    Route::delete('entities/{entity}', [EntityController::class, 'destroy'])->name('entities.destroy');
    Route::post('entities/{entity}/restore', [EntityController::class, 'restore'])->name('entities.restore');

    Route::get('entities/{entity}/children', [EntityTreeController::class, 'children'])->name('entities.children');
    Route::get('entities/{entity}/descendants', [EntityTreeController::class, 'descendants'])->name('entities.descendants');
    Route::get('entities/{entity}/ancestors', [EntityTreeController::class, 'ancestors'])->name('entities.ancestors');
    Route::get('entities/{entity}/siblings', [EntityTreeController::class, 'siblings'])->name('entities.siblings');
    Route::get('entities/{entity}/tree', [EntityTreeController::class, 'tree'])->name('entities.tree');

    Route::get('entities/{entity}/contents', [EntityContentsController::class, 'index'])->name('entities.contents.index');
    Route::put('entities/{entity}/contents/{lang}', [EntityContentsController::class, 'update'])->name('entities.contents.update');
    Route::delete('entities/{entity}/contents/{lang}/{field}', [EntityContentsController::class, 'destroy'])->name('entities.contents.destroy');

    Route::get('entities/{entity}/relations', [EntityRelationsController::class, 'index'])->name('entities.relations.index');
    Route::post('entities/{entity}/relations', [EntityRelationsController::class, 'store'])->name('entities.relations.store');
    Route::patch('entities/{entity}/relations/{called}/{kind}', [EntityRelationsController::class, 'update'])->name('entities.relations.update');
    Route::delete('entities/{entity}/relations/{called}/{kind}', [EntityRelationsController::class, 'destroy'])->name('entities.relations.destroy');
});
