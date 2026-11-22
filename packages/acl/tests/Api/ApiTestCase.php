<?php

namespace KusikusiCMS\Acl\Tests\Api;

use KusikusiCMS\Acl\AclServiceProvider;
use KusikusiCMS\Acl\Tests\Fixtures\ApiUser;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Api\ApiServiceProvider;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQuery;
use KusikusiCMS\Models\ModelsServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;

/**
 * acl together with kusikusicms/api, to test the acl contribution.
 */
abstract class ApiTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            PermissionServiceProvider::class,
            SanctumServiceProvider::class,
            ApiServiceProvider::class,
            AclServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', ApiUser::class);
    }

    protected function tearDown(): void
    {
        EntityResource::flushExtensions();
        EntityQuery::flush();

        parent::tearDown();
    }

    /**
     * A user holding the given permission names directly.
     */
    protected function userWith(string ...$permissions): ApiUser
    {
        /** @var ApiUser $user */
        $user = ApiUser::query()->forceCreate([
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
