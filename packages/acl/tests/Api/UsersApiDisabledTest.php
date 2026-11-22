<?php

namespace KusikusiCMS\Acl\Tests\Api;

use KusikusiCMS\Acl\Tests\Fixtures\UserWithoutRoles;

class UsersApiDisabledTest extends ApiTestCase
{
    public function test_disabled_by_config(): void
    {
        $this->assertUsersApiAbsent();
    }

    public function test_absent_when_the_user_model_has_no_roles(): void
    {
        $this->assertUsersApiAbsent();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if ($this->name() === 'test_disabled_by_config') {
            $app['config']->set('kusikusicms.acl.api.users.enabled', false);
        } else {
            $app['config']->set('auth.providers.users.model', UserWithoutRoles::class);
        }
    }

    protected function assertUsersApiAbsent(): void
    {
        $this->getJson('/api/v1/users')->assertNotFound();
        $this->getJson('/api/v1/users/1/roles')->assertNotFound();
        $this->getJson('/api/v1/capabilities')
            ->assertJsonPath('data.packages.1.name', 'acl')
            ->assertJsonPath('data.packages.1.features', ['can-flags', 'filtered-lists', 'me.permissions', 'roles', 'permissions.manage', 'entity-access']);
    }
}
