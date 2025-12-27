<?php

namespace KusikusiCMS\Admin;

use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin.php', 'kusikusicms.admin');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/admin.php' => config_path('kusikusicms/admin.php'),
        ], 'kusikusicms-config');

        // Views (if present)
        if (is_dir(__DIR__.'/../resources/views')) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'kusikusicms-admin');
        }

        // Routes (if present)
        if (file_exists(__DIR__.'/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
