<?php

use Illuminate\Support\Facades\Route;
use KusikusiCMS\Acl\Api\Http\Controllers\MePermissionsController;

// Loaded inside the kusikusicms/api route group (prefix, middleware, 'kusikusicms.api.' names).
Route::middleware((array) config('kusikusicms.api.auth_middleware'))->group(function () {
    Route::get('me/permissions', MePermissionsController::class)->name('me.permissions');
});
