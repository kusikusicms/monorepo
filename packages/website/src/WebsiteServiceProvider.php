<?php

namespace KusikusiCMS\Website;

use Illuminate\Support\ServiceProvider;

class WebsiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/website.php', 'kusikusicms.website');
    }

    public function boot(): void
    {
        // Load routes if present and enabled
        if (config('kusikusicms.website.routes.enabled', true) && file_exists(__DIR__.'/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->publishes([
            __DIR__.'/../config/website.php' => config_path('kusikusicms/website.php'),
        ], ['kusikusicms-config', 'kusikusicms-website-config']);
    }
}
