<?php

namespace KusikusiCMS\Acl\Actions;

use KusikusiCMS\Acl\Actions\Concerns\WritesAcl;
use KusikusiCMS\Acl\Exceptions\LastManagerException;
use KusikusiCMS\Acl\Exceptions\ProtectedRoleException;
use Spatie\Permission\Models\Role;

/**
 * Deletes a role. The guest role cannot be deleted, and the last role that keeps a manager cannot either.
 */
final class DeleteRole
{
    use WritesAcl;

    /**
     * @throws ProtectedRoleException
     * @throws LastManagerException
     */
    public function __invoke(Role $role): void
    {
        $this->protectGuestRole($role, 'deleted');

        $this->write(fn () => $role->delete(), checkManagers: true);
    }
}
