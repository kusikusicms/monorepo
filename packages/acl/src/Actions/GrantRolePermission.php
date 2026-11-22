<?php

namespace KusikusiCMS\Acl\Actions;

use KusikusiCMS\Acl\Actions\Concerns\WritesAcl;
use KusikusiCMS\Acl\Support\EntityPermission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Grants one entity permission to a role. Idempotent: the permission row is created when missing.
 */
final class GrantRolePermission
{
    use WritesAcl;

    public function __invoke(Role $role, EntityPermission $permission): Role
    {
        return $this->write(function () use ($role, $permission) {
            $role->permissions()->syncWithoutDetaching([
                Permission::findOrCreate($permission->name(), $this->guardName())->getKey(),
            ]);
            $role->unsetRelation('permissions');

            return $role;
        });
    }
}
