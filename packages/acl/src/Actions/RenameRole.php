<?php

namespace KusikusiCMS\Acl\Actions;

use KusikusiCMS\Acl\Actions\Concerns\WritesAcl;
use KusikusiCMS\Acl\Exceptions\ProtectedRoleException;
use Spatie\Permission\Models\Role;

/**
 * Renames a role. The guest role cannot be renamed.
 */
final class RenameRole
{
    use WritesAcl;

    /**
     * @throws ProtectedRoleException
     */
    public function __invoke(Role $role, string $name): Role
    {
        if ($role->name === $name) {
            return $role;
        }

        $this->protectGuestRole($role, 'renamed');

        return $this->write(function () use ($role, $name) {
            $role->name = $name;
            $role->save();

            return $role;
        });
    }
}
