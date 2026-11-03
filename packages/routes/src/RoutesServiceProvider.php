<?php

namespace KusikusiCMS\Routes;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Models\Events\EntityContentDeleted;
use KusikusiCMS\Models\Events\EntityContentSaved;
use KusikusiCMS\Models\Events\EntityContentSaving;
use KusikusiCMS\Models\Events\EntitySaved;
use KusikusiCMS\Models\Events\EntitySaving;
use KusikusiCMS\Routes\Console\InstallCommand;
use KusikusiCMS\Routes\Listeners\SyncEntityRoutes;
use KusikusiCMS\Routes\Listeners\SyncSlugRoutes;
use KusikusiCMS\Routes\Listeners\ValidateSlugRoutes;
use KusikusiCMS\Routes\Services\PathBuilder;
use KusikusiCMS\Routes\Services\RouteManager;

class RoutesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/routes.php', 'kusikusicms.routes');
        $this->app->singleton(PathBuilder::class);
        $this->app->singleton(RouteManager::class);
    }

    /**
     * Boot the package: route syncing listeners, about entry and publishable resources.
     */
    public function boot(): void
    {
        Event::listen(EntityContentSaving::class, ValidateSlugRoutes::class);
        Event::listen([EntityContentSaved::class, EntityContentDeleted::class], SyncSlugRoutes::class);
        Event::listen(EntitySaving::class, [SyncEntityRoutes::class, 'validate']);
        Event::listen(EntitySaved::class, [SyncEntityRoutes::class, 'handle']);
        AboutCommand::add('KusikusiCMS routes package', fn () => ['Version' => $this->packageVersion()]);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        if (config('kusikusicms.routes.catch_all.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/routes.php' => config_path('kusikusicms/routes.php'),
            ], ['kusikusicms-config', 'kusikusicms-routes-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'kusikusicms-routes-migrations');

            $this->publishes([
                InstallCommand::stubPath('WebsiteController.stub') => app_path('Http/Controllers/WebsiteController.php'),
                InstallCommand::stubPath('views/html/home.blade.stub') => resource_path('views/html/home.blade.php'),
            ], 'kusikusicms-routes-stubs');

            $this->commands([
                InstallCommand::class,
            ]);
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
