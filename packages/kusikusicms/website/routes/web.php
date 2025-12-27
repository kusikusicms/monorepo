<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('kusikusicms.website.route_prefix', ''),
], function () {
    Route::get('/kusikusicms-health', function () {
        return response()->json(['status' => 'ok', 'package' => 'kusikusicms/website']);
    });
});
