<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Abilities;
use KusikusiCMS\Acl\Exceptions\LastManagerException;
use KusikusiCMS\Acl\Services\ManagerGuard;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ManagePermissionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function test_a_user_with_the_manage_permission_passes_every_ability(): void
    {
        $user = $this->userWithPermission('kusikusicms.acl.manage');

        foreach (Abilities::all() as $ability) {
            $this->assertTrue(Gate::forUser($user)->allows($ability), $ability);
        }
    }

    public function test_a_user_without_the_manage_permission_fails_every_ability(): void
    {
        $user = User::query()->forceCreate(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);

        foreach (Abilities::all() as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability), $ability);
        }
    }

    public function test_a_guest_fails_every_ability(): void
    {
        foreach (Abilities::all() as $ability) {
            $this->assertFalse(Gate::forUser(null)->allows($ability), $ability);
        }
    }

    public function test_a_role_with_the_wildcard_counts_as_a_manager(): void
    {
        $user = $this->userWithPermission('*');

        $this->assertTrue(Gate::forUser($user)->allows(Abilities::MANAGE_ROLES));
    }

    public function test_the_manage_permission_granted_through_a_role_counts(): void
    {
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo(Permission::findOrCreate('kusikusicms.acl.manage', 'web'));
        $user = User::query()->forceCreate(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);
        $user->assignRole($role);

        $this->assertTrue(Gate::forUser($user)->allows(Abilities::MANAGE_ROLES));
    }

    public function test_ensure_manager_remains_passes_with_a_direct_manager(): void
    {
        $user = $this->userWithPermission('kusikusicms.acl.manage');

        app(ManagerGuard::class)->ensureManagerRemains();

        $this->assertSame(1, app(ManagerGuard::class)->countManagers());
        $this->assertTrue($user->fresh()->can('kusikusicms.acl.manage'));
    }

    public function test_ensure_manager_remains_throws_when_nobody_manages(): void
    {
        User::query()->forceCreate(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);

        $this->expectException(LastManagerException::class);

        app(ManagerGuard::class)->ensureManagerRemains();
    }

    public function test_ensure_manager_remains_throws_when_the_only_manager_role_has_no_user(): void
    {
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo(Permission::findOrCreate('kusikusicms.acl.manage', 'web'));

        $this->expectException(LastManagerException::class);

        app(ManagerGuard::class)->ensureManagerRemains();
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::query()->forceCreate(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));

        return $user;
    }
}
