<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Abilities;
use KusikusiCMS\Acl\Actions\GrantRolePermission;
use KusikusiCMS\Acl\Actions\RevokeRolePermission;
use KusikusiCMS\Acl\Actions\SyncRolePermissions;
use KusikusiCMS\Acl\Api\Http\Controllers\Concerns\ResolvesRoles;
use KusikusiCMS\Acl\Api\Http\Requests\RolePermissionRequest;
use KusikusiCMS\Acl\Api\Http\Requests\SyncRolePermissionsRequest;
use KusikusiCMS\Acl\Api\Http\Resources\RoleResource;
use Spatie\Permission\Models\Role;

class RolePermissionsController extends Controller
{
    use ResolvesRoles;

    public function index(string $role): JsonResponse
    {
        Gate::authorize(Abilities::VIEW_ROLES);

        return $this->permissions($this->findRole($role));
    }

    public function update(SyncRolePermissionsRequest $request, string $role, SyncRolePermissions $sync): JsonResponse
    {
        return $this->permissions($sync($this->findRole($role), $request->entityPermissions(), $request->manage()));
    }

    public function store(RolePermissionRequest $request, string $role, GrantRolePermission $grant): JsonResponse
    {
        return $this->permissions($grant($this->findRole($role), $request->entityPermission()))->setStatusCode(201);
    }

    public function destroy(RolePermissionRequest $request, string $role, RevokeRolePermission $revoke): Response
    {
        $revoke($this->findRole($role), $request->entityPermission());

        return response()->noContent();
    }

    protected function permissions(Role $role): JsonResponse
    {
        return response()->json(['data' => RoleResource::permissions($role->load('permissions'))]);
    }
}
