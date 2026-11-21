<?php

namespace KusikusiCMS\Routes\Tests\Api;

use KusikusiCMS\Acl\AclServiceProvider;
use KusikusiCMS\Api\ApiServiceProvider;
use KusikusiCMS\Models\ModelsServiceProvider;
use KusikusiCMS\Routes\RoutesServiceProvider;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;

/**
 * routes + acl + api: acl maps the route abilities to `update` on the entity.
 */
class RoutesApiWithAclTest extends ApiTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            PermissionServiceProvider::class,
            RoutesServiceProvider::class,
            AclServiceProvider::class,
            SanctumServiceProvider::class,
            ApiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('permission.enable_wildcard_permission', true);
    }

    /**
     * Reuse the acl test migrations for the Spatie permission tables.
     */
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/acl/tests/Fixtures/Migrations');
    }

    public function test_route_abilities_follow_update_permission(): void
    {
        $this->seedSite();

        $user = $this->makeUser();
        foreach (['entities.view', 'entities.update.about.self'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/entities/about/routes', ['path' => '/about-us'])->assertCreated();
        $this->postJson('/api/v1/entities/blog/routes', ['path' => '/news'])->assertForbidden();
    }
}
