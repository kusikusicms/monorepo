<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Abilities;
use KusikusiCMS\Acl\Acl;
use KusikusiCMS\Acl\Actions\CreateRole;
use KusikusiCMS\Acl\Actions\DeleteRole;
use KusikusiCMS\Acl\Actions\RenameRole;
use KusikusiCMS\Acl\Api\Http\Controllers\Concerns\ResolvesRoles;
use KusikusiCMS\Acl\Api\Http\Requests\StoreRoleRequest;
use KusikusiCMS\Acl\Api\Http\Requests\UpdateRoleRequest;
use KusikusiCMS\Acl\Api\Http\Resources\RoleResource;
use KusikusiCMS\Api\Support\Pagination;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    use ResolvesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(Abilities::VIEW_ROLES);

        $search = $request->query('search');

        $page = Role::query()
            ->where('guard_name', (string) config('kusikusicms.acl.guard_name', 'web'))
            ->when(is_string($search) && $search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->withCount('permissions')
            ->addSelect(['users_count' => $this->usersCount()])
            ->orderBy('name')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return RoleResource::collection($page);
    }

    /**
     * Users of the acl user model holding the role. Spatie's Role::users() needs a guard on the instance,
     * so the count is a plain subquery.
     */
    protected function usersCount(): Builder
    {
        $roles = (string) config('permission.table_names.roles', 'roles');

        return DB::table((string) config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->selectRaw('count(*)')
            ->whereColumn('role_id', $roles.'.id')
            ->where('model_type', Acl::userModel() ?? '');
    }

    public function store(StoreRoleRequest $request, CreateRole $create): JsonResponse
    {
        $role = $create((string) $request->validated('name'));

        return RoleResource::make($role->load('permissions'))->response()->setStatusCode(201);
    }

    public function show(string $role): RoleResource
    {
        Gate::authorize(Abilities::VIEW_ROLES);

        return RoleResource::make($this->findRole($role)->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, string $role, RenameRole $rename): RoleResource
    {
        return RoleResource::make($rename($this->findRole($role), (string) $request->validated('name'))->load('permissions'));
    }

    public function destroy(string $role, DeleteRole $delete): Response
    {
        Gate::authorize(Abilities::MANAGE_ROLES);

        $delete($this->findRole($role));

        return response()->noContent();
    }
}
