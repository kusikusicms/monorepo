<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API prefix and middleware
    |--------------------------------------------------------------------------
    */

    'prefix' => env('KUSIKUSICMS_API_PREFIX', 'api/v1'),

    'middleware' => ['api'],

    'auth_middleware' => ['auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | guard: the guard whose user provider checks credentials (null = default).
    | token_expiration: minutes until a personal access token expires (null = never).
    | login_throttle: "max_attempts,minutes" per email and IP.
    */

    'auth' => [
        'guard' => null,
        'token_expiration' => null,
        'login_throttle' => env('KUSIKUSICMS_API_LOGIN_THROTTLE', '5,1'),

        /*
        | SPA cookie/session authentication (Sanctum stateful). Only requests whose
        | Origin/Referer is listed in `sanctum.stateful` get a session and CSRF checks.
        | guard: the session guard to log into; it must match `sanctum.guard`.
        | remember: whether `remember: true` in the request is honoured.
        */
        'spa' => [
            'enabled' => (bool) env('KUSIKUSICMS_API_SPA', true),
            'guard' => 'web',
            'remember' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'per_page' => 25,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization for abilities no handler answered
    |--------------------------------------------------------------------------
    |
    | read: 'allow' or 'deny' for view-like abilities (authenticated users only).
    | write: 'allow' or 'deny' for every other ability.
    */

    'authorization' => [
        'unhandled' => [
            'read' => env('KUSIKUSICMS_API_UNHANDLED_READ', 'allow'),
            'write' => env('KUSIKUSICMS_API_UNHANDLED_WRITE', 'deny'),
        ],
    ],

];
