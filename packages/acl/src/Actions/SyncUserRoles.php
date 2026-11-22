<?php

namespace KusikusiCMS\Acl\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use KusikusiCMS\Acl\Actions\Concerns\WritesAcl;
use KusikusiCMS\Acl\Exceptions\LastManagerException;
use KusikusiCMS\Acl\Exceptions\ProtectedRoleException;
use Spatie\Permission\Models\Role;

/**
 * Replaces the roles of a user (in the acl guard). The guest role cannot be assigned, and the change is
 * refused when it would leave no manager.
 */
final class SyncUserRoles
{
    use WritesAcl;

    /**
     * @param  Model  $user  A model using Spatie's HasRoles.
     * @param  list<string>  $roleNames
     *
     * @throws ProtectedRoleException When the guest role is in the list.
     * @throws LastManagerException
     */
    public function __invoke(Model $user, array $roleNames): void
    {
        foreach ($roleNames as $name) {
            if ($this->isGuestRole($name)) {
                throw new ProtectedRoleException("The guest role [{$name}] cannot be assigned to users.");
            }
        }

        $this->write(function () use ($user, $roleNames) {
            $guard = $this->guardName();
            $ids = Role::query()
                ->where('guard_name', $guard)
                ->whereIn('name', array_values(array_unique($roleNames)))
                ->pluck('id')
                ->all();

            /** @var BelongsToMany<Role, Model> $relation */
            $relation = $user->roles(); // @phpstan-ignore method.notFound (HasRoles)

            // Roles of other guards are kept: only the acl guard's roles are replaced.
            $others = $relation->getQuery()->where('guard_name', '!=', $guard)->pluck($relation->getRelated()->getQualifiedKeyName())->all();

            $relation->sync(array_merge($others, $ids));
            $user->unsetRelation('roles');
        }, checkManagers: true);
    }
}
