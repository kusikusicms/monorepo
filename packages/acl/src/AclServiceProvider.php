<?php

namespace KusikusiCMS\Acl;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Acl\Services\PermissionResolver;

class AclServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/acl.php', 'kusikusicms.acl');
        $this->app->singleton(PermissionResolver::class);
    }

    /**
     * Boot the package's about entry and publishable configuration.
     */
    public function boot(): void
    {
        AboutCommand::add('KusikusiCMS ACL package', fn () => ['Version' => $this->packageVersion()]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/acl.php' => config_path('kusikusicms/acl.php'),
            ], ['kusikusicms-config', 'kusikusicms-acl-config']);
        }
    }

    /**
     * Resolve the installed package version from Composer, falling back to "dev".
     */
    protected function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('kusikusicms/acl')) {
            return InstalledVersions::getPrettyVersion('kusikusicms/acl') ?? 'dev';
        }

        return 'dev';
    }
}
