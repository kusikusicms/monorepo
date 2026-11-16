<?php

namespace KusikusiCMS\Acl;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Acl\Builders\EntityAclMacros;
use KusikusiCMS\Acl\Console\CheckCommand;
use KusikusiCMS\Acl\Console\CreatePermissionCommand;
use KusikusiCMS\Acl\Console\InstallCommand;
use KusikusiCMS\Acl\Listeners\AuthorizeEntityWrites;
use KusikusiCMS\Acl\Policies\EntityPolicy;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Events\EntityContentDeleting;
use KusikusiCMS\Models\Events\EntityContentSaving;
use KusikusiCMS\Models\Events\EntityCreating;
use KusikusiCMS\Models\Events\EntityDeleting;
use KusikusiCMS\Models\Events\EntityRestoring;
use KusikusiCMS\Models\Events\EntitySaved;
use KusikusiCMS\Models\Events\EntityUpdating;

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
        EntityAclMacros::register();
        Gate::policy(Entity::class, EntityPolicy::class);
        Event::listen(EntitySaved::class, function (): void {
            $this->app->make(PermissionResolver::class)->clearCache();
        });

        if (config('kusikusicms.acl.guard.enabled', false)) {
            Event::listen(EntityCreating::class, [AuthorizeEntityWrites::class, 'creating']);
            Event::listen(EntityUpdating::class, [AuthorizeEntityWrites::class, 'updating']);
            Event::listen(EntityDeleting::class, [AuthorizeEntityWrites::class, 'deleting']);
            Event::listen(EntityRestoring::class, [AuthorizeEntityWrites::class, 'restoring']);
            Event::listen(EntityContentSaving::class, [AuthorizeEntityWrites::class, 'contentSaving']);
            Event::listen(EntityContentDeleting::class, [AuthorizeEntityWrites::class, 'contentDeleting']);
        }

        AboutCommand::add('KusikusiCMS ACL package', fn () => [
            'Version' => $this->packageVersion(),
            'Wildcard permissions' => config('permission.enable_wildcard_permission') ? 'enabled' : '<fg=red;options=bold>DISABLED</>',
            'Guest role' => config('kusikusicms.acl.guest_role') ?? 'none',
            'Event guard' => config('kusikusicms.acl.guard.enabled') ? 'enabled' : 'disabled',
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreatePermissionCommand::class,
                CheckCommand::class,
            ]);
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
