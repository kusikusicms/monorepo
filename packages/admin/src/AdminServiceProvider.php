<?php

namespace KusikusiCMS\Admin;

use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin.php', 'kusikusicms.admin');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin.php' => config_path('kusikusicms/admin.php'),
        ], ['kusikusicms-config', 'kusikusicms-admin-config']);

        // Views (if present)
        if (is_dir(__DIR__.'/../resources/views')) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'kusikusicms-admin');
        }

        // Routes (if present and enabled)
        if (config('kusikusicms.admin.routes.enabled', true) && file_exists(__DIR__.'/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
