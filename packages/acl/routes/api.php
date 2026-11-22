<?php

use Illuminate\Support\Facades\Route;
use KusikusiCMS\Acl\Acl;
use KusikusiCMS\Acl\Api\Http\Controllers\EntityAccessController;
use KusikusiCMS\Acl\Api\Http\Controllers\MePermissionsController;
use KusikusiCMS\Acl\Api\Http\Controllers\RolePermissionsController;
use KusikusiCMS\Acl\Api\Http\Controllers\RolesController;
use KusikusiCMS\Acl\Api\Http\Controllers\UserRolesController;
use KusikusiCMS\Acl\Api\Http\Controllers\UserSearchController;

// Loaded inside the kusikusicms/api route group (prefix, middleware, 'kusikusicms.api.' names).
Route::middleware((array) config('kusikusicms.api.auth_middleware'))->group(function () {
    Route::get('me/permissions', MePermissionsController::class)->name('me.permissions');

    Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
    Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}', [RolesController::class, 'show'])->name('roles.show');
    Route::patch('roles/{role}', [RolesController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');

    Route::get('roles/{role}/permissions', [RolePermissionsController::class, 'index'])->name('roles.permissions.index');
    Route::put('roles/{role}/permissions', [RolePermissionsController::class, 'update'])->name('roles.permissions.update');
    Route::post('roles/{role}/permissions', [RolePermissionsController::class, 'store'])->name('roles.permissions.store');
    Route::delete('roles/{role}/permissions', [RolePermissionsController::class, 'destroy'])->name('roles.permissions.destroy');

    Route::get('entities/{entity}/permissions', EntityAccessController::class)->name('entities.permissions');

    // Only when `kusikusicms.acl.api.users.enabled` is true and the user model uses HasRoles.
    if (Acl::usersApiEnabled()) {
        Route::get('users', UserSearchController::class)->name('users.index');
        Route::get('users/{user}/roles', [UserRolesController::class, 'show'])->name('users.roles.show');
        Route::put('users/{user}/roles', [UserRolesController::class, 'update'])->name('users.roles.update');
    }
});
