<?php

namespace KusikusiCMS\Acl\Actions;

use KusikusiCMS\Acl\Acl;
use KusikusiCMS\Acl\Actions\Concerns\WritesAcl;
use KusikusiCMS\Acl\Exceptions\LastManagerException;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Acl\Support\RolePermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Replaces the entity permissions of a role and, when `$manage` is not null, sets the manage permission.
 * Other non-entity permissions are kept.
 */
final class SyncRolePermissions
{
    use WritesAcl;

    /**
     * @param  list<EntityPermission>  $permissions
     *
     * @throws LastManagerException When removing manage would leave no manager.
     */
    public function __invoke(Role $role, array $permissions, ?bool $manage = null): Role
    {
        return $this->write(function () use ($role, $permissions, $manage) {
            $guard = $this->guardName();
            $managePermission = Acl::managePermission();
            $keep = [];

            foreach ($role->permissions()->get() as $permission) {
                $name = (string) $permission->getAttribute('name');

                if (RolePermissions::isEntityPermission($name)) {
                    continue;
                }

                if ($name === $managePermission && $manage !== null) {
                    continue;
                }

                $keep[] = $permission->getKey();
            }

            foreach ($permissions as $permission) {
                $keep[] = Permission::findOrCreate($permission->name(), $guard)->getKey();
            }

            if ($manage === true) {
                $keep[] = Permission::findOrCreate($managePermission, $guard)->getKey();
            }

            $role->permissions()->sync(array_values(array_unique($keep)));
            $role->unsetRelation('permissions');

            return $role;
        }, checkManagers: $manage === false);
    }
}
