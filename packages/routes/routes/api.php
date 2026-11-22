<?php

use Illuminate\Support\Facades\Route;
use KusikusiCMS\Routes\Api\Http\Controllers\EntityRoutesController;

// Loaded inside the kusikusicms/api route group (prefix, middleware, 'kusikusicms.api.' names).
Route::middleware((array) config('kusikusicms.api.auth_middleware'))->group(function () {
    Route::get('routes/resolve', [EntityRoutesController::class, 'resolve'])->name('routes.resolve');
    Route::delete('routes/{route}', [EntityRoutesController::class, 'destroy'])->whereNumber('route')->name('routes.destroy');
    Route::get('entities/{entity}/routes', [EntityRoutesController::class, 'index'])->name('entities.routes.index');
    Route::post('entities/{entity}/routes', [EntityRoutesController::class, 'store'])->name('entities.routes.store');
});
