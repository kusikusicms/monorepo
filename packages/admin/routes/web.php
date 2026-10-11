<?php

use Illuminate\Support\Facades\Route;

Route::middleware(config('kusikusicms.admin.routes.middleware', ['web']))
    ->prefix(config('kusikusicms.admin.routes.prefix', 'admin'))
    ->group(function () {
        Route::get('/', function () {
            return view('kusikusicms-admin::dashboard', [
                'package' => 'kusikusicms/admin',
            ]);
        })->name('kusikusicms.admin.dashboard');
    });
