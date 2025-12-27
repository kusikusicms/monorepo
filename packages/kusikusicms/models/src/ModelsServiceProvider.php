<?php

namespace KusikusiCMS\Models;

use Illuminate\Support\ServiceProvider;

class ModelsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register bindings, config, etc.
        $this->mergeConfigFrom(__DIR__.'/../config/models.php', 'kusikusicms.models');
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/models.php' => config_path('kusikusicms/models.php'),
        ], 'kusikusicms-config');
    }
}
