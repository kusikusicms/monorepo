<?php

return [
    // Configuration options for KusikusiCMS Media package

    'routes' => [
        // Set to false to skip registering the package routes.
        'enabled' => true,
        'prefix' => 'media',
        'middleware' => ['web'],
    ],
];
