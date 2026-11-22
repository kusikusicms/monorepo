<?php

namespace KusikusiCMS\Acl\Actions;

use KusikusiCMS\Acl\Actions\Concerns\WritesAcl;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;
use KusikusiCMS\Acl\Support\EntityPermission;
use KusikusiCMS\Acl\Support\RolePermissions;
use Spatie\Permission\Models\Role;

/**
 * Revokes one entity permission from a role. Matches the same actions, ids and scopes in any order,
 * and never deletes the permission row.
 */
final class RevokeRolePermission
{
    use WritesAcl;

    public function __invoke(Role $role, EntityPermission $permission): Role
    {
        return $this->write(function () use ($role, $permission) {
            $ids = [];

            foreach ($role->permissions()->get() as $held) {
                try {
                    $parsed = EntityPermission::parse((string) $held->getAttribute('name'));
                } catch (InvalidEntityPermissionException) {
                    continue;
                }

                if ($parsed !== null && RolePermissions::same($parsed, $permission)) {
                    $ids[] = $held->getKey();
                }
            }

            if ($ids !== []) {
                $role->permissions()->detach($ids);
            }
            $role->unsetRelation('permissions');

            return $role;
        });
    }
}
