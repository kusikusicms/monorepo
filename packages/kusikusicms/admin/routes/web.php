<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->prefix(config('kusikusicms.admin.route_prefix', 'admin'))
    ->group(function () {
        Route::get('/', function () {
            return view('kusikusicms-admin::dashboard', [
                'package' => 'kusikusicms/admin',
            ]);
        })->name('kusikusicms.admin.dashboard');
    });
