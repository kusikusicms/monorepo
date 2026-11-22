<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Abilities;
use KusikusiCMS\Acl\Actions\SyncUserRoles;
use KusikusiCMS\Acl\Api\Http\Controllers\Concerns\ResolvesUsers;
use KusikusiCMS\Acl\Api\Http\Requests\SyncUserRolesRequest;

class UserRolesController extends Controller
{
    use ResolvesUsers;

    public function show(string $user): JsonResponse
    {
        Gate::authorize(Abilities::ASSIGN_ROLES);

        return $this->roles($user);
    }

    public function update(SyncUserRolesRequest $request, string $user, SyncUserRoles $sync): JsonResponse
    {
        $sync($this->findUser($user), $request->roleNames());

        return $this->roles($user);
    }

    protected function roles(string $user): JsonResponse
    {
        return response()->json(['data' => $this->findUser($user)->getRelation('roles')->pluck('name')->values()->all()]);
    }
}
