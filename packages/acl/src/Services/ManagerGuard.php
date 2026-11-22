<?php

namespace KusikusiCMS\Acl\Services;

use Illuminate\Support\Facades\DB;
use KusikusiCMS\Acl\Acl;
use KusikusiCMS\Acl\Exceptions\LastManagerException;

/**
 * Keeps at least one user able to manage roles. Counts users holding the manage permission,
 * through a role or directly, in one query.
 */
final class ManagerGuard
{
    /**
     * @throws LastManagerException When no user holds the manage permission.
     */
    public function ensureManagerRemains(): void
    {
        if ($this->countManagers() === 0) {
            throw new LastManagerException('At least one user must keep the permission to manage roles.');
        }
    }

    /**
     * The number of distinct users holding the manage permission (or the wildcard '*') in the guard.
     */
    public function countManagers(): int
    {
        $guard = (string) config('kusikusicms.acl.guard_name');
        $userModel = Acl::userModel() ?? '';
        $names = [Acl::managePermission(), '*'];

        $permissions = (string) config('permission.table_names.permissions', 'permissions');
        $roles = (string) config('permission.table_names.roles', 'roles');
        $modelHasRoles = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelHasPermissions = (string) config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $roleHasPermissions = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');

        $viaRoles = DB::table($modelHasRoles.' as mhr')
            ->join($roleHasPermissions.' as rhp', 'rhp.role_id', '=', 'mhr.role_id')
            ->join($permissions.' as p', 'p.id', '=', 'rhp.permission_id')
            ->join($roles.' as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', $userModel)
            ->where('r.guard_name', $guard)
            ->where('p.guard_name', $guard)
            ->whereIn('p.name', $names)
            ->select('mhr.model_id as user_id');

        $direct = DB::table($modelHasPermissions.' as mhp')
            ->join($permissions.' as p', 'p.id', '=', 'mhp.permission_id')
            ->where('mhp.model_type', $userModel)
            ->where('p.guard_name', $guard)
            ->whereIn('p.name', $names)
            ->select('mhp.model_id as user_id');

        return (int) DB::query()
            ->fromSub($viaRoles->union($direct), 'managers')
            ->count();
    }
}
