<?php

use Illuminate\Support\Facades\Route;
use KusikusiCMS\Routes\Http\Controllers\RoutesController;

/*
 * Catch-all route resolving stored entity paths. Registered as a fallback route,
 * so routes defined by the application always win.
 */
Route::middleware((array) config('kusikusicms.routes.catch_all.middleware', ['web']))
    ->get('{path?}', RoutesController::class)
    ->where('path', '.*')
    ->fallback()
    ->name('kusikusicms.routes.catch-all');
