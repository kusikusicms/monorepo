<?php

namespace KusikusiCMS\Acl\Tests\Api;

use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Tests\Fixtures\ApiUser;
use KusikusiCMS\Models\Entity;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionsApiTest extends ApiTestCase
{
    protected Role $admin;

    protected Role $editor;

    protected ApiUser $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Entity::query()->create(['id' => 'root', 'model' => 'home']);
        Entity::query()->create(['id' => 'blog', 'model' => 'section', 'parent_entity_id' => 'root']);
        Entity::query()->create(['id' => 'post1', 'model' => 'post', 'parent_entity_id' => 'blog']);

        $this->admin = Role::findOrCreate('admin', 'web');
        $this->admin->givePermissionTo(Permission::findOrCreate('kusikusicms.acl.manage', 'web'));
        $this->editor = Role::findOrCreate('editor', 'web');
        $this->manager = $this->userWith();
        $this->manager->assignRole($this->admin);
        Sanctum::actingAs($this->manager);
    }

    public function test_sync_replaces_entity_permissions_and_keeps_others(): void
    {
        $this->editor->givePermissionTo(
            Permission::findOrCreate('entities.view.root.self', 'web'),
            Permission::findOrCreate('reports.view', 'web'),
        );

        $this->putJson($this->url($this->editor), ['permissions' => [
            ['action' => ['create', 'update'], 'entity_id' => 'blog', 'scope' => ['self', 'descendants']],
            ['action' => '*', 'entity_id' => '*', 'scope' => 'self'],
        ]])
            ->assertOk()
            ->assertJsonPath('data.entity', [
                ['name' => 'entities.create,update.blog.self,descendants', 'action' => ['create', 'update'], 'entity_id' => ['blog'], 'scope' => ['self', 'descendants']],
                ['name' => 'entities.*.*.self', 'action' => ['*'], 'entity_id' => ['*'], 'scope' => ['self']],
            ])
            ->assertJsonPath('data.manage', false)
            ->assertJsonPath('data.other', ['reports.view']);

        $this->assertNotContains('entities.view.root.self', $this->editor->fresh()->permissions->pluck('name')->all());
    }

    public function test_get_round_trips_the_permissions(): void
    {
        $this->putJson($this->url($this->editor), ['permissions' => [
            ['action' => ['view', 'update'], 'entity_id' => ['blog', 'post1'], 'scope' => '*'],
        ], 'manage' => true])->assertOk();

        $this->getJson($this->url($this->editor))
            ->assertOk()
            ->assertExactJson(['data' => [
                'entity' => [['name' => 'entities.view,update.blog,post1.*', 'action' => ['view', 'update'], 'entity_id' => ['blog', 'post1'], 'scope' => ['*']]],
                'manage' => true,
                'other' => [],
            ]]);
    }

    public function test_sync_without_manage_keeps_the_manage_permission(): void
    {
        $this->putJson($this->url($this->admin), ['permissions' => []])
            ->assertOk()
            ->assertJsonPath('data.manage', true);
    }

    public function test_removing_manage_from_the_last_manager_role_is_refused(): void
    {
        $this->putJson($this->url($this->admin), ['permissions' => [], 'manage' => false])
            ->assertStatus(409)
            ->assertJsonPath('code', 'last_manager');

        $this->assertTrue($this->admin->fresh()->hasPermissionTo('kusikusicms.acl.manage'));
    }

    public function test_removing_manage_is_allowed_when_another_manager_remains(): void
    {
        $this->userWith('kusikusicms.acl.manage');

        $this->putJson($this->url($this->admin), ['permissions' => [], 'manage' => false])
            ->assertOk()
            ->assertJsonPath('data.manage', false);
    }

    public function test_grant_and_revoke_a_single_permission(): void
    {
        $payload = ['action' => 'update', 'entity_id' => 'blog', 'scope' => 'descendants'];

        $this->postJson($this->url($this->editor), $payload)
            ->assertCreated()
            ->assertJsonPath('data.entity.0.name', 'entities.update.blog.descendants');
        $this->postJson($this->url($this->editor), $payload)->assertCreated()->assertJsonCount(1, 'data.entity');

        $this->deleteJson($this->url($this->editor), $payload)->assertNoContent();

        $this->assertSame(0, $this->editor->fresh()->permissions()->count());
        $this->assertSame(1, Permission::query()->where('name', 'entities.update.blog.descendants')->count());
    }

    public function test_revoke_matches_parts_in_any_order(): void
    {
        $this->editor->givePermissionTo(Permission::findOrCreate('entities.update,view.blog.self', 'web'));

        $this->deleteJson($this->url($this->editor), ['action' => ['view', 'update'], 'entity_id' => 'blog', 'scope' => 'self'])
            ->assertNoContent();

        $this->assertSame(0, $this->editor->fresh()->permissions()->count());
    }

    public function test_invalid_parts_are_rejected(): void
    {
        $this->postJson($this->url($this->editor), ['action' => 'publish', 'entity_id' => 'blog', 'scope' => 'self'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['permission']]);
        $this->postJson($this->url($this->editor), ['action' => 'view', 'entity_id' => 'blog', 'scope' => 'siblings'])
            ->assertUnprocessable();
        $this->postJson($this->url($this->editor), ['action' => 'view', 'entity_id' => 'a.b', 'scope' => 'self'])
            ->assertUnprocessable();
        $this->postJson($this->url($this->editor), ['action' => 'view'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['entity_id', 'scope']]);
    }

    public function test_unknown_entity_ids_are_rejected(): void
    {
        $this->postJson($this->url($this->editor), ['action' => 'view', 'entity_id' => ['blog', 'missing'], 'scope' => 'self'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['entity_id']]);

        $this->putJson($this->url($this->editor), ['permissions' => [
            ['action' => 'view', 'entity_id' => 'blog', 'scope' => 'self'],
            ['action' => 'view', 'entity_id' => 'nope', 'scope' => 'self'],
        ]])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['permissions.1.entity_id']]);
    }

    public function test_a_grant_is_seen_by_the_policy_immediately(): void
    {
        $user = $this->userWith();
        $user->assignRole($this->editor);
        $post = Entity::query()->findOrFail('post1');

        $this->assertFalse(Gate::forUser($user)->allows('update', $post));

        $this->postJson($this->url($this->editor), ['action' => 'update', 'entity_id' => 'blog', 'scope' => 'descendants'])
            ->assertCreated();

        $this->assertTrue(Gate::forUser($user->fresh())->allows('update', $post));
    }

    public function test_the_guest_role_permissions_can_be_edited(): void
    {
        $guest = Role::findOrCreate('guest', 'web');

        $this->postJson($this->url($guest), ['action' => 'view', 'entity_id' => '*', 'scope' => '*'])->assertCreated();
    }

    protected function url(Role $role): string
    {
        return "/api/v1/roles/{$role->getKey()}/permissions";
    }
}
