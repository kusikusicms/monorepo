<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use KusikusiCMS\Acl\Actions\CreateRole;
use KusikusiCMS\Acl\Actions\DeleteRole;
use KusikusiCMS\Acl\Actions\GrantRolePermission;
use KusikusiCMS\Acl\Actions\RenameRole;
use KusikusiCMS\Acl\Actions\RevokeRolePermission;
use KusikusiCMS\Acl\Actions\SyncRolePermissions;
use KusikusiCMS\Acl\Exceptions\LastManagerException;
use KusikusiCMS\Acl\Exceptions\ProtectedRoleException;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Acl\Tests\Fixtures\User;
use KusikusiCMS\Acl\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The role actions work without kusikusicms/api.
 */
class RoleActionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function test_create_rename_and_delete(): void
    {
        $role = app(CreateRole::class)('editor');
        $this->assertSame('web', $role->guard_name);

        app(RenameRole::class)($role, 'writer');
        $this->assertSame('writer', $role->fresh()->name);

        app(DeleteRole::class)($role);
        $this->assertNull(Role::query()->find($role->getKey()));
    }

    public function test_the_guest_role_is_protected(): void
    {
        $guest = Role::findOrCreate('guest', 'web');

        $this->expectException(ProtectedRoleException::class);

        app(DeleteRole::class)($guest);
    }

    public function test_sync_keeps_unrelated_permissions_and_sets_manage(): void
    {
        $role = Role::findOrCreate('editor', 'web');
        $role->givePermissionTo(Permission::findOrCreate('entities.view', 'web'), Permission::findOrCreate('reports.view', 'web'));

        app(SyncRolePermissions::class)($role, [EntityPermission::make('update', 'blog', 'self')], true);

        $this->assertEqualsCanonicalizing(
            ['entities.update.blog.self', 'reports.view', 'kusikusicms.acl.manage'],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_removing_manage_from_the_last_manager_rolls_back(): void
    {
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo(Permission::findOrCreate('kusikusicms.acl.manage', 'web'), Permission::findOrCreate('entities.view', 'web'));
        $user = User::query()->forceCreate(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x']);
        $user->assignRole($role);

        try {
            app(SyncRolePermissions::class)($role, [], false);
            $this->fail('Expected LastManagerException');
        } catch (LastManagerException) {
        }

        $this->assertEqualsCanonicalizing(
            ['kusikusicms.acl.manage', 'entities.view'],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_grant_is_idempotent_and_revoke_keeps_the_permission_row(): void
    {
        $role = Role::findOrCreate('editor', 'web');
        $permission = EntityPermission::make(['view', 'update'], 'blog', 'descendants');

        app(GrantRolePermission::class)($role, $permission);
        app(GrantRolePermission::class)($role, $permission);
        $this->assertSame(1, $role->fresh()->permissions()->count());

        app(RevokeRolePermission::class)($role, EntityPermission::make(['update', 'view'], 'blog', 'descendants'));
        $this->assertSame(0, $role->fresh()->permissions()->count());
        $this->assertSame(1, Permission::query()->where('name', 'entities.view,update.blog.descendants')->count());
    }
}
