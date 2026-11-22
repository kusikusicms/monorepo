<?php

namespace KusikusiCMS\Acl\Tests\Api;

use Illuminate\Support\Facades\DB;
use KusikusiCMS\Acl\Tests\Fixtures\ApiUser;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesApiTest extends ApiTestCase
{
    protected Role $admin;

    protected ApiUser $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Role::findOrCreate('admin', 'web');
        $this->admin->givePermissionTo(Permission::findOrCreate('kusikusicms.acl.manage', 'web'));
        $this->manager = $this->userWith();
        $this->manager->assignRole($this->admin);
        Sanctum::actingAs($this->manager);
    }

    public function test_non_managers_are_forbidden_on_every_route(): void
    {
        Sanctum::actingAs($this->userWith('entities.view'));
        $id = $this->admin->getKey();

        $this->getJson('/api/v1/roles')->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->postJson('/api/v1/roles', ['name' => 'x'])->assertForbidden();
        $this->getJson("/api/v1/roles/{$id}")->assertForbidden();
        $this->patchJson("/api/v1/roles/{$id}", ['name' => 'x'])->assertForbidden();
        $this->deleteJson("/api/v1/roles/{$id}")->assertForbidden();
        $this->getJson("/api/v1/roles/{$id}/permissions")->assertForbidden();
        $this->putJson("/api/v1/roles/{$id}/permissions", ['permissions' => []])->assertForbidden();
        $this->postJson("/api/v1/roles/{$id}/permissions", ['action' => 'view', 'entity_id' => '*', 'scope' => '*'])->assertForbidden();
        $this->deleteJson("/api/v1/roles/{$id}/permissions", ['action' => 'view', 'entity_id' => '*', 'scope' => '*'])->assertForbidden();
    }

    public function test_create_a_role(): void
    {
        $this->postJson('/api/v1/roles', ['name' => 'editor'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'editor')
            ->assertJsonPath('data.permissions', ['entity' => [], 'manage' => false, 'other' => []]);

        $this->assertNotNull(Role::findByName('editor', 'web'));
    }

    public function test_a_duplicate_name_in_the_guard_is_rejected(): void
    {
        $this->postJson('/api/v1/roles', ['name' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_rename_a_role(): void
    {
        $editor = Role::findOrCreate('editor', 'web');

        $this->patchJson("/api/v1/roles/{$editor->getKey()}", ['name' => 'writer'])
            ->assertOk()
            ->assertJsonPath('data.name', 'writer');

        $this->patchJson("/api/v1/roles/{$editor->getKey()}", ['name' => 'admin'])->assertUnprocessable();
    }

    public function test_delete_a_role(): void
    {
        $editor = Role::findOrCreate('editor', 'web');

        $this->deleteJson("/api/v1/roles/{$editor->getKey()}")->assertNoContent();

        $this->assertSame(0, Role::query()->where('name', 'editor')->count());
    }

    public function test_the_guest_role_cannot_be_renamed_or_deleted(): void
    {
        $guest = Role::findOrCreate('guest', 'web');

        $this->patchJson("/api/v1/roles/{$guest->getKey()}", ['name' => 'visitors'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'protected_role');
        $this->deleteJson("/api/v1/roles/{$guest->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'protected_role');

        $this->assertNotNull(Role::findByName('guest', 'web'));
    }

    public function test_deleting_the_last_manager_role_is_refused(): void
    {
        $this->deleteJson("/api/v1/roles/{$this->admin->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'last_manager');

        $this->assertNotNull(Role::findByName('admin', 'web'));
    }

    public function test_a_role_of_another_guard_is_not_found(): void
    {
        $api = Role::create(['name' => 'other', 'guard_name' => 'api']);

        $this->getJson("/api/v1/roles/{$api->getKey()}")->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_list_roles_with_counts_and_search(): void
    {
        $editor = Role::findOrCreate('editor', 'web');
        $editor->givePermissionTo(Permission::findOrCreate('entities.view', 'web'), Permission::findOrCreate('entities.update', 'web'));
        Role::create(['name' => 'other', 'guard_name' => 'api']);

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['admin', 'editor'])
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.permissions_count', 1)
            ->assertJsonPath('data.1.users_count', 0)
            ->assertJsonPath('data.1.permissions_count', 2)
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/roles?search=edit')->assertOk()->assertJsonPath('data.*.name', ['editor']);
    }

    public function test_the_role_list_runs_a_constant_number_of_queries(): void
    {
        $this->getJson('/api/v1/roles')->assertOk();
        $small = $this->countQueries('/api/v1/roles');

        foreach (range(1, 10) as $i) {
            Role::findOrCreate("role$i", 'web')->givePermissionTo(Permission::findOrCreate("entities.view.e$i", 'web'));
        }

        $this->assertSame($small, $this->countQueries('/api/v1/roles'));
    }

    protected function countQueries(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
