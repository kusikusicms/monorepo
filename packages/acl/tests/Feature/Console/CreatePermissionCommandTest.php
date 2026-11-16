<?php

namespace KusikusiCMS\Acl\Tests\Feature\Console;

use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreatePermissionCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Entity::create(['id' => 'home']);
        Entity::create(['id' => 'xY1a5G', 'parent_entity_id' => 'home']);
    }

    public function test_it_builds_the_permission_name_from_options(): void
    {
        $this->artisan('k:acl:create-permission', [
            '--action' => ['create', 'update'],
            '--entity' => ['home'],
            '--scope' => ['self,children'],
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('entities.create,update.home.self,children')
            ->assertSuccessful();

        $this->assertTrue(Permission::query()->where('name', 'entities.create,update.home.self,children')->where('guard_name', 'web')->exists());
    }

    public function test_entity_and_scope_default_to_wildcards(): void
    {
        $this->artisan('k:acl:create-permission', ['--action' => ['view'], '--no-interaction' => true])->assertSuccessful();

        $this->assertTrue(Permission::query()->where('name', 'entities.view.*.*')->exists());
    }

    public function test_a_wildcard_selection_replaces_the_other_values(): void
    {
        $this->artisan('k:acl:create-permission', ['--action' => ['view', '*'], '--entity' => ['xY1a5G'], '--scope' => ['self'], '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertTrue(Permission::query()->where('name', 'entities.*.xY1a5G.self')->exists());
    }

    public function test_role_option_creates_the_role_and_attaches_the_permission(): void
    {
        $this->artisan('k:acl:create-permission', [
            '--action' => ['update'],
            '--entity' => ['home'],
            '--scope' => ['descendants'],
            '--role' => 'editor',
            '--guard' => 'api',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $role = Role::findByName('editor', 'api');
        $this->assertSame(['entities.update.home.descendants'], $role->permissions->pluck('name')->all());
    }

    public function test_unknown_entities_and_invalid_parts_fail(): void
    {
        $this->artisan('k:acl:create-permission', ['--action' => ['view'], '--entity' => ['xy1a5g'], '--no-interaction' => true])
            ->expectsOutputToContain('xy1a5g')
            ->assertFailed();
        $this->artisan('k:acl:create-permission', ['--action' => ['publish'], '--no-interaction' => true])->assertFailed();
        $this->artisan('k:acl:create-permission', ['--no-interaction' => true])->assertFailed();

        $this->assertSame(0, Permission::query()->count());
    }

    public function test_it_warns_about_create_with_only_the_self_scope(): void
    {
        $this->artisan('k:acl:create-permission', ['--action' => ['create'], '--entity' => ['home'], '--scope' => ['self'], '--no-interaction' => true])
            ->expectsOutputToContain('grants nothing')
            ->assertSuccessful();
    }

    public function test_interactive_prompts(): void
    {
        $this->artisan('k:acl:create-permission')
            ->expectsChoice('Which actions does the permission allow?', ['view', 'update'], ['*', 'All actions', 'view', 'view', 'create', 'create', 'update', 'update', 'delete', 'delete'])
            ->expectsQuestion('Which entity ids? (separated by commas, * for all)', 'home')
            ->expectsChoice('Which entities, relative to the entity ids?', ['self', 'descendants'], ['*', 'All scopes', 'self', 'self', 'children', 'children', 'descendants', 'descendants'])
            ->expectsQuestion('Attach it to a role? (empty for none)', 'author')
            ->assertSuccessful();

        $this->assertSame(['entities.view,update.home.self,descendants'], Role::findByName('author', 'web')->permissions->pluck('name')->all());
    }
}
