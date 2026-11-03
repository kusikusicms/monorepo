<?php

namespace KusikusiCMS\Routes;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class RoutesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/routes.php', 'kusikusicms.routes');
    }

    /**
     * Boot the package: about entry and publishable resources.
     */
    public function boot(): void
    {
        AboutCommand::add('KusikusiCMS routes package', fn () => ['Version' => $this->packageVersion()]);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/routes.php' => config_path('kusikusicms/routes.php'),
            ], ['kusikusicms-config', 'kusikusicms-routes-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'kusikusicms-routes-migrations');
        }
    }

    /**
     * Resolve the installed package version from Composer, falling back to "dev".
     */
    protected function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('kusikusicms/routes')) {
            return InstalledVersions::getPrettyVersion('kusikusicms/routes') ?? 'dev';
        }

        return 'dev';
    }
}
