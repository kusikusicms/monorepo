<?php

return [
    // Configuration options for KusikusiCMS Admin package

    'routes' => [
        // Set to false to skip registering the package routes.
        'enabled' => true,
        'prefix' => 'admin',
        'middleware' => ['web'],
    ],
];
