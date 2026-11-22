<?php

namespace KusikusiCMS\Acl\Tests\Api;

use KusikusiCMS\Models\Entity;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EntityAccessApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Entity::query()->create(['id' => 'root', 'model' => 'home']);
        Entity::query()->create(['id' => 'blog', 'model' => 'section', 'parent_entity_id' => 'root']);
        Entity::query()->create(['id' => 'post1', 'model' => 'post', 'parent_entity_id' => 'blog']);
    }

    public function test_managers_see_each_role_access(): void
    {
        $admin = Role::findOrCreate('admin', 'web');
        $admin->givePermissionTo(Permission::findOrCreate('kusikusicms.acl.manage', 'web'));
        Role::findOrCreate('editor', 'web')->givePermissionTo(Permission::findOrCreate('entities.create,update.blog.descendants', 'web'));
        Sanctum::actingAs($this->userWith('kusikusicms.acl.manage'));

        $this->getJson('/api/v1/entities/post1/permissions')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['role' => 'admin', 'actions' => ['view' => false, 'create' => false, 'update' => false, 'delete' => false], 'via' => []],
                ['role' => 'editor', 'actions' => ['view' => false, 'create' => true, 'update' => true, 'delete' => false], 'via' => [
                    ['name' => 'entities.create,update.blog.descendants', 'action' => ['create', 'update'], 'entity_id' => ['blog'], 'scope' => ['descendants']],
                ]],
            ]]);
    }

    public function test_non_managers_are_forbidden(): void
    {
        Sanctum::actingAs($this->userWith('entities.view'));

        $this->getJson('/api/v1/entities/post1/permissions')->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_an_unknown_entity_is_not_found(): void
    {
        Sanctum::actingAs($this->userWith('kusikusicms.acl.manage'));

        $this->getJson('/api/v1/entities/missing/permissions')->assertNotFound()->assertJsonPath('code', 'not_found');
    }
}
