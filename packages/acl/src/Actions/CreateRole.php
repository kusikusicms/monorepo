<?php

namespace KusikusiCMS\Acl\Actions;

use KusikusiCMS\Acl\Actions\Concerns\WritesAcl;
use Spatie\Permission\Models\Role;

/**
 * Creates a role in the acl guard.
 */
final class CreateRole
{
    use WritesAcl;

    public function __invoke(string $name): Role
    {
        return $this->write(function () use ($name): Role {
            /** @var Role $role */
            $role = Role::create(['name' => $name, 'guard_name' => $this->guardName()]);

            return $role;
        });
    }
}
