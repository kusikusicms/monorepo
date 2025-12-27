<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('kusikusicms.media.route_prefix', 'media'),
    'middleware' => ['web'],
], function () {
    Route::get('/kusikusicms-health', function () {
        return response()->json(['status' => 'ok', 'package' => 'kusikusicms/media']);
    });
});
