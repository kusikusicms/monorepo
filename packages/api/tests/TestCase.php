<?php

namespace KusikusiCMS\Api\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use KusikusiCMS\Api\ApiServiceProvider;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQuery;
use KusikusiCMS\Api\Tests\Fixtures\User;
use KusikusiCMS\Models\ModelsServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            SanctumServiceProvider::class,
            ApiServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('sanctum.guard', ['web']);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    /**
     * @param  Application  $app
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/../../vendor/laravel/sanctum/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Migrations');
    }

    /**
     * The resource and query hooks are static, so clear them between tests.
     */
    protected function tearDown(): void
    {
        EntityResource::flushExtensions();
        EntityQuery::flush();

        parent::tearDown();
    }
}
