<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use KusikusiCMS\Acl\Api\Http\Controllers\Concerns\ResolvesUsers;
use KusikusiCMS\Acl\Api\Http\Requests\UserSearchRequest;
use KusikusiCMS\Acl\Api\Http\Resources\AclUserResource;
use KusikusiCMS\Api\Support\Pagination;

/**
 * GET /users?search=: a minimal, read-only user search for role assignment.
 */
class UserSearchController extends Controller
{
    use ResolvesUsers;

    public function __invoke(UserSearchRequest $request): AnonymousResourceCollection
    {
        $search = (string) $request->validated('search', '');
        $columns = array_values(array_filter(
            (array) config('kusikusicms.acl.api.users.search_columns', ['name', 'email']),
            'is_string',
        ));

        $query = $this->users();

        if ($search !== '' && $columns !== []) {
            $query->where(function ($query) use ($columns, $search): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        $page = $query
            ->orderBy($query->getModel()->getKeyName())
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return AclUserResource::collection($page);
    }
}
