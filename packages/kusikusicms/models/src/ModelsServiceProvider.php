<?php

namespace KusikusiCMS\Models;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class ModelsServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/models.php', 'kusikusicms.models');
    }

    /**
     * Boots the service by publishing the package's configuration file to the application's configuration path.
     */
    public function boot(): void
    {
        AboutCommand::add('KusikusiCMS core models package', fn () => ['Version' => '12.0.0-alpha.1']);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__
                .'/../config/models.php' => config_path('kusikusicms/models.php'),
            ], 'kusikusicms-config');
        }
    }
}
