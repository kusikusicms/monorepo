<?php

namespace KusikusiCMS\Routes\Tests\Api;

use KusikusiCMS\Api\ApiServiceProvider;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQuery;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\ModelsServiceProvider;
use KusikusiCMS\Routes\RoutesServiceProvider;
use KusikusiCMS\Routes\Tests\Fixtures\ApiUser;
use KusikusiCMS\Routes\Tests\TestCase;
use Laravel\Sanctum\SanctumServiceProvider;

/**
 * routes together with kusikusicms/api (no acl), to test the routes contribution.
 */
abstract class ApiTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ModelsServiceProvider::class,
            RoutesServiceProvider::class,
            SanctumServiceProvider::class,
            ApiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', ApiUser::class);
        $app['config']->set('kusikusicms.routes.domains', ['localhost' => ['root_entity_id' => 'home', 'lang' => 'en']]);
    }

    protected function tearDown(): void
    {
        EntityResource::flushExtensions();
        EntityQuery::flush();

        parent::tearDown();
    }

    protected function makeUser(): ApiUser
    {
        /** @var ApiUser $user */
        $user = ApiUser::query()->forceCreate([
            'name' => 'User',
            'email' => uniqid('user', true).'@example.com',
            'password' => 'x',
        ]);

        return $user;
    }

    /**
     * home (/) > about (/about), home > blog (/blog).
     */
    protected function seedSite(): void
    {
        Entity::query()->create(['id' => 'home', 'model' => 'home']);
        Entity::query()->create(['id' => 'about', 'model' => 'page', 'parent_entity_id' => 'home'])
            ->createContents(['title' => 'About', 'slug' => 'about'], 'en');
        Entity::query()->create(['id' => 'blog', 'model' => 'section', 'parent_entity_id' => 'home'])
            ->createContents(['title' => 'Blog', 'slug' => 'blog'], 'en');
    }
}
