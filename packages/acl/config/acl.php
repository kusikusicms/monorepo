<?php

return [
    'prefix' => 'entities',
    'guard_name' => 'web',
    'guest_role' => 'guest',
    'guard' => [
        'enabled' => env('KUSIKUSICMS_ACL_GUARD', false),
    ],
];
