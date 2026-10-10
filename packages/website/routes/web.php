<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('kusikusicms.website.routes.prefix', ''),
    'middleware' => config('kusikusicms.website.routes.middleware', []),
], function () {
    Route::get('/kusikusicms-health', function () {
        return response()->json(['status' => 'ok', 'package' => 'kusikusicms/website']);
    });
});
