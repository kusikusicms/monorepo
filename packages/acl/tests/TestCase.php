<?php

namespace KusikusiCMS\Acl\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use KusikusiCMS\Acl\AclServiceProvider;
use KusikusiCMS\Models\ModelsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;

use function Orchestra\Testbench\default_migration_path;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            PermissionServiceProvider::class,
            AclServiceProvider::class,
        ];
    }

    /**
     * Enforce SQLite foreign keys, set an app key, and enable wildcard permissions.
     */
    protected function defineEnvironment($app): void
    {
        $connection = $app['config']->get('database.default');
        $app['config']->set("database.connections.{$connection}.foreign_key_constraints", true);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('permission.enable_wildcard_permission', true);
    }

    /**
     * Load Laravel's default migrations for the Entity user foreign keys.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(default_migration_path());
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Migrations');
    }
}
