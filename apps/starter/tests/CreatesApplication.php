<?php

namespace Tests;

use Illuminate\Foundation\Application;
use KusikusiCMS\Models\ModelsServiceProvider;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Register package providers explicitly to ensure migrations/config/events are available in tests
        $app->register(ModelsServiceProvider::class);

        return $app;
    }
}
