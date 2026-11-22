<?php

return [
    'prefix' => 'entities',
    'guard_name' => 'web',
    'guest_role' => 'guest',
    // Non-entity Spatie permission that lets a user manage roles, role permissions and user roles through the API.
    'manage_permission' => 'kusikusicms.acl.manage',
    'guard' => [
        'enabled' => env('KUSIKUSICMS_ACL_GUARD', false),
    ],
    // Contribution to kusikusicms/api (`can` flags, filtered lists, GET /me/permissions), used only when it is installed.
    'api' => [
        'enabled' => env('KUSIKUSICMS_ACL_API', true),
        'users' => [
            'enabled' => env('KUSIKUSICMS_ACL_API_USERS', true),
            'search_columns' => ['name', 'email'],
        ],
    ],
];
