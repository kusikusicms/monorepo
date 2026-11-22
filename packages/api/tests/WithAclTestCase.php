<?php

namespace KusikusiCMS\Api\Tests;

use Illuminate\Foundation\Application;
use KusikusiCMS\Acl\AclServiceProvider;
use KusikusiCMS\Api\ApiServiceProvider;
use KusikusiCMS\Api\Tests\Fixtures\AclUser;
use KusikusiCMS\Models\ModelsServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;

/**
 * api + acl: the acl EntityPolicy answers the Entity abilities.
 */
abstract class WithAclTestCase extends TestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            PermissionServiceProvider::class,
            AclServiceProvider::class,
            SanctumServiceProvider::class,
            ApiServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AclUser::class);
        $app['config']->set('permission.enable_wildcard_permission', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/Fixtures/AclMigrations');
    }

    /**
     * A user holding the given permission names directly.
     */
    protected function userWith(string ...$permissions): AclUser
    {
        /** @var AclUser $user */
        $user = AclUser::query()->create([
            'name' => 'User',
            'email' => uniqid('user', true).'@example.com',
            'password' => 'x',
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
