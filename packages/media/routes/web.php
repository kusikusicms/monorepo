<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('kusikusicms.media.routes.prefix', 'media'),
    'middleware' => config('kusikusicms.media.routes.middleware', ['web']),
], function () {
    Route::get('/kusikusicms-health', function () {
        return response()->json(['status' => 'ok', 'package' => 'kusikusicms/media']);
    });
});
