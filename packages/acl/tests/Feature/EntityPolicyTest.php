<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Policies\EntityPolicy;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EntityPolicyTest extends TestCase
{
    private Entity $home;

    private Entity $blog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = Entity::create(['id' => 'home']);
        $this->blog = Entity::create(['id' => 'blog', 'parent_entity_id' => 'home']);
        Entity::refreshAncestorRelationsOfEntity($this->home);
    }

    public function test_provider_registers_entity_policy_with_gate(): void
    {
        $this->assertInstanceOf(EntityPolicy::class, Gate::getPolicyFor(Entity::class));
    }

    public function test_view_any_is_allowed_when_user_has_any_view_permission(): void
    {
        $user = $this->userWithPermission('entities.view.home.self');

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Entity::class));
        $this->assertFalse(Gate::forUser($this->makeUser())->allows('viewAny', Entity::class));
    }

    public function test_guests_and_users_inherit_the_guest_role_permission(): void
    {
        Role::findOrCreate('guest', 'web')->givePermissionTo($this->permission('entities.view.*.*'));

        $this->assertTrue(Gate::forUser(null)->allows('view', $this->blog));
        $this->assertTrue(Gate::forUser($this->makeUser())->allows('view', $this->blog));
    }

    public function test_create_policy_accepts_parent_argument_and_guest_permission(): void
    {
        Role::findOrCreate('guest', 'web')->givePermissionTo($this->permission('entities.create.home.children'));

        $this->assertTrue(Gate::forUser(null)->allows('create', [Entity::class, 'home']));
        $this->assertFalse(Gate::forUser(null)->allows('create', [Entity::class, 'blog']));
    }

    public function test_restore_and_force_delete_use_the_delete_action(): void
    {
        $user = $this->userWithPermission('entities.delete.home.self');
        $gate = Gate::forUser($user);

        $this->assertTrue($gate->allows('delete', $this->home));
        $this->assertTrue($gate->allows('restore', $this->home));
        $this->assertTrue($gate->allows('forceDelete', $this->home));
    }

    private function userWithPermission(string $name): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo($this->permission($name));

        return $user;
    }

    private function makeUser(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'ACL policy test user',
            'email' => uniqid('acl-policy-', true).'@example.test',
            'password' => 'password',
        ])->save();

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }
}
