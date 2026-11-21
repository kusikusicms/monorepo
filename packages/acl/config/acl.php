<?php

return [
    'prefix' => 'entities',
    'guard_name' => 'web',
    'guest_role' => 'guest',
    'guard' => [
        'enabled' => env('KUSIKUSICMS_ACL_GUARD', false),
    ],
    // Contribution to kusikusicms/api (`can` flags, filtered lists, GET /me/permissions), used only when it is installed.
    'api' => [
        'enabled' => env('KUSIKUSICMS_ACL_API', true),
    ],
];
