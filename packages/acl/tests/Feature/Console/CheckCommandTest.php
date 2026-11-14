<?php

namespace KusikusiCMS\Acl\Tests\Feature\Console;

use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CheckCommandTest extends TestCase
{
    private User $user;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $home = Entity::create(['id' => 'home']);
        Entity::create(['id' => 'blog', 'parent_entity_id' => 'home']);
        Entity::create(['id' => 'post1', 'parent_entity_id' => 'blog']);
        Entity::refreshAncestorRelationsOfEntity($home);

        $this->user = new User;
        $this->user->forceFill(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'password'])->save();
        $this->user->givePermissionTo(Permission::findOrCreate('entities.update.home.descendants', 'web'));
    }

    public function test_it_reports_the_matching_permission_for_a_user(): void
    {
        $this->artisan('k:acl:check', ['action' => 'update', 'entity' => 'post1', '--user' => (string) $this->user->getKey()])
            ->expectsOutputToContain('matched entities.update.home.descendants')
            ->assertSuccessful();
    }

    public function test_it_reports_denied_actions_with_a_failure_code(): void
    {
        $this->artisan('k:acl:check', ['action' => 'update', 'entity' => 'home', '--user' => (string) $this->user->getKey()])
            ->expectsOutputToContain('Denied')
            ->assertFailed();
    }

    public function test_it_checks_the_guest_when_no_user_is_given(): void
    {
        $this->artisan('k:acl:check', ['action' => 'view', 'entity' => 'blog'])->assertFailed();

        Role::findOrCreate('guest', 'web')->givePermissionTo(Permission::findOrCreate('entities.view.*.*', 'web'));

        $this->artisan('k:acl:check', ['action' => 'view', 'entity' => 'blog'])
            ->expectsOutputToContain('The guest can view [blog] (matched entities.view.blog.self)')
            ->assertSuccessful();
    }

    public function test_create_is_checked_on_the_parent(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('entities.create.blog.children', 'web'));

        $this->artisan('k:acl:check', ['action' => 'create', 'entity' => 'blog', '--user' => (string) $this->user->getKey()])
            ->expectsOutputToContain('children of [blog] (matched entities.create.blog.children)')
            ->assertSuccessful();
        $this->artisan('k:acl:check', ['action' => 'create', 'entity' => 'post1', '--user' => (string) $this->user->getKey()])
            ->assertFailed();
    }

    public function test_invalid_input_fails(): void
    {
        $this->artisan('k:acl:check', ['action' => 'publish', 'entity' => 'home'])->assertFailed();
        $this->artisan('k:acl:check', ['action' => 'view', 'entity' => 'HOME'])->assertFailed();
        $this->artisan('k:acl:check', ['action' => 'view', 'entity' => 'home', '--user' => '999'])->assertFailed();
    }

    public function test_it_warns_when_wildcards_are_disabled(): void
    {
        config(['permission.enable_wildcard_permission' => false]);

        $this->artisan('k:acl:check', ['action' => 'view', 'entity' => 'home'])
            ->expectsOutputToContain('Wildcard permissions are disabled');
    }
}
