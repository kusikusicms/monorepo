<?php

namespace KusikusiCMS\Acl\Actions\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;
use KusikusiCMS\Acl\Exceptions\ProtectedRoleException;
use KusikusiCMS\Acl\Services\ManagerGuard;
use KusikusiCMS\Acl\Services\PermissionResolver;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared behaviour of the role and user-role actions: one transaction, an optional last-manager check
 * before commit, and both permission caches cleared once the change is committed.
 *
 * The last-manager check refuses a change that takes the number of managers from at least one to zero.
 * When there was no manager before (for example a fresh install), the change is allowed.
 */
trait WritesAcl
{
    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    protected function write(Closure $change, bool $checkManagers = false): mixed
    {
        $result = DB::transaction(function () use ($change, $checkManagers) {
            $guard = app(ManagerGuard::class);
            $hadManagers = $checkManagers && $guard->countManagers() > 0;

            $result = $change();

            if ($hadManagers) {
                $guard->ensureManagerRemains();
            }

            return $result;
        });

        self::clearCaches();

        return $result;
    }

    /**
     * Clear Spatie's permission cache and the acl resolver cache.
     */
    public static function clearCaches(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionResolver::class)->clearCache();
    }

    protected function guardName(): string
    {
        return (string) config('kusikusicms.acl.guard_name', 'web');
    }

    protected function isGuestRole(Role|string $role): bool
    {
        $name = $role instanceof Role ? $role->name : $role;

        return $name === config('kusikusicms.acl.guest_role');
    }

    /**
     * @throws ProtectedRoleException
     */
    protected function protectGuestRole(Role $role, string $operation): void
    {
        if ($this->isGuestRole($role)) {
            throw new ProtectedRoleException("The guest role [{$role->name}] cannot be {$operation}.");
        }
    }
}
