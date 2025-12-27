<?php

namespace KusikusiCMS\Website;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WebsiteServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/website.php', 'kusikusicms.website');
    }

    public function boot()
    {
        // Load routes if present
        if (file_exists(__DIR__.'/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->publishes([
            __DIR__.'/../config/website.php' => config_path('kusikusicms/website.php'),
        ], 'kusikusicms-config');
    }
}
