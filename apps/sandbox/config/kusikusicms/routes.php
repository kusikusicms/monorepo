<?php

use App\Http\Controllers\WebsiteController;

return [
    /*
     * Entity models that get routes. Use '*' for all models, or a list of model names (e.g. 'Home', 'Section').
     */
    'models' => ['*'],

    /*
     * Entity models that never get routes, even when 'models' contains '*'.
     */
    'except_models' => [],

    /*
     * The EntityContent field used to build paths.
     */
    'slug_field' => 'slug',

    /*
     * Sites served by this application, keyed by host. Version 13.x supports one entry:
     * paths are globally unique, so there is one site per database.
     * - root_entity_id: only this entity and its descendants get routes (null = all entities).
     * - lang: the primary language of the site.
     */
    'domains' => [
        'sandbox.test' => [
            'root_entity_id' => 'home',
            'lang' => 'en',
        ],
    ],

    /*
     * The catch-all fallback route that resolves stored paths.
     */
    'catch_all' => [
        'enabled' => true,
        'middleware' => ['web'],
    ],

    /*
     * The host application controller that renders resolved routes.
     */
    'controller' => WebsiteController::class,

    /*
     * Allowed formats (URL extensions). Requests with other extensions return 404.
     */
    'formats' => ['html', 'rss', 'amp', 'json', 'xml'],

    'default_format' => 'html',
];
