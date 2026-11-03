<?php

namespace KusikusiCMS\Routes\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use KusikusiCMS\Models\ModelsServiceProvider;
use KusikusiCMS\Routes\RoutesServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

use function Orchestra\Testbench\default_migration_path;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            RoutesServiceProvider::class,
        ];
    }

    /**
     * Enforce foreign keys on SQLite so FK cascades behave as in production.
     */
    protected function defineEnvironment($app): void
    {
        $connection = $app['config']->get('database.default');
        $app['config']->set("database.connections.{$connection}.foreign_key_constraints", true);
    }

    /**
     * Load Laravel's default migrations: entities reference the users table (created_by/updated_by).
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(default_migration_path());
    }
}
