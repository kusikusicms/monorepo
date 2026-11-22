<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Api\Http\Requests\EntityIndexRequest;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQuery;
use KusikusiCMS\Api\Services\EntityQueryBuilder;
use KusikusiCMS\Api\Support\Pagination;
use KusikusiCMS\Models\Entity;

/**
 * Tree queries: children, descendants, ancestors and siblings lists (same params as /entities) and a nested tree.
 */
class EntityTreeController extends Controller
{
    public function __construct(private EntityQueryBuilder $queries) {}

    public function children(EntityIndexRequest $request, string $entity): AnonymousResourceCollection
    {
        return $this->list($request, 'children', $entity);
    }

    public function descendants(EntityIndexRequest $request, string $entity): AnonymousResourceCollection
    {
        return $this->list($request, 'descendants', $entity);
    }

    public function ancestors(EntityIndexRequest $request, string $entity): AnonymousResourceCollection
    {
        return $this->list($request, 'ancestors', $entity);
    }

    public function siblings(EntityIndexRequest $request, string $entity): AnonymousResourceCollection
    {
        return $this->list($request, 'siblings', $entity);
    }

    /**
     * The entity with its descendants nested in `children`, down to ?depth= (default 99).
     */
    public function tree(Request $request, string $entity): JsonResponse
    {
        $request->validate(['depth' => ['sometimes', 'integer', 'min:1', 'max:99']]);

        $root = Entity::query()->findOrFail($entity);
        Gate::authorize('view', $root);

        $query = Entity::query()->select('entities.*')->descendantsOf($root->id, [
            'maxDepth' => (int) $request->query('depth', '99'),
            'includeSelf' => true,
            'includeRelationMeta' => false,
        ])->orderBy('entities.id');

        $this->queries->applyContents($query, $request);
        $this->queries->applyIncludes($query, $request);
        EntityQuery::apply($query, $request, 'index');

        $tree = $query->get()->toTree()->firstWhere('id', $root->id);

        return response()->json(['data' => $tree === null ? null : EntityResource::make($tree)->resolve($request)]);
    }

    protected function list(EntityIndexRequest $request, string $scope, string $entity): AnonymousResourceCollection
    {
        $page = $this->queries->build($request, [$scope, $entity])
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return EntityResource::collection($page);
    }
}
