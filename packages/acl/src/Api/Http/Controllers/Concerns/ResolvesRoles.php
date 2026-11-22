<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers\Concerns;

use Spatie\Permission\Models\Role;

trait ResolvesRoles
{
    /**
     * A role of the acl guard by id, or 404.
     */
    protected function findRole(int|string $id): Role
    {
        /** @var Role */
        return Role::query()
            ->where('guard_name', (string) config('kusikusicms.acl.guard_name', 'web'))
            ->findOrFail($id);
    }
}
