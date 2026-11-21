<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Api\Actions\CreateEntity;
use KusikusiCMS\Api\Actions\UpdateEntity;
use KusikusiCMS\Api\Http\Requests\EntityIndexRequest;
use KusikusiCMS\Api\Http\Requests\StoreEntityRequest;
use KusikusiCMS\Api\Http\Requests\UpdateEntityRequest;
use KusikusiCMS\Api\Http\Resources\EntityResource;
use KusikusiCMS\Api\Services\EntityQueryBuilder;
use KusikusiCMS\Api\Support\Pagination;
use KusikusiCMS\Models\Entity;

class EntityController extends Controller
{
    public function __construct(private EntityQueryBuilder $queries) {}

    /**
     * Paginated, filtered list of entities.
     */
    public function index(EntityIndexRequest $request): AnonymousResourceCollection
    {
        $page = $this->queries->build($request)->paginate(Pagination::perPage($request))->withQueryString();

        return EntityResource::collection($page);
    }

    public function store(StoreEntityRequest $request, CreateEntity $create): JsonResponse
    {
        $entity = $create($request->validated());

        return EntityResource::make($this->reload($request, $entity->id))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $entity): EntityResource
    {
        $model = $this->queries->single($request, $request->query('trashed') === 'with')->findOrFail($entity);

        Gate::authorize('view', $model);

        return EntityResource::make($model);
    }

    public function update(UpdateEntityRequest $request, UpdateEntity $update): EntityResource
    {
        $entity = $update($request->entity(), $request->validated());

        return EntityResource::make($this->reload($request, $entity->id));
    }

    public function destroy(string $entity): JsonResponse
    {
        $model = Entity::query()->findOrFail($entity);

        Gate::authorize('delete', $model);

        $model->delete();

        return response()->json(null, 204);
    }

    public function restore(Request $request, string $entity): EntityResource
    {
        $model = Entity::query()->onlyTrashed()->findOrFail($entity);

        Gate::authorize('restore', $model);

        $model->restore();

        return EntityResource::make($this->reload($request, $model->id));
    }

    /**
     * Reload an entity after a write, with the same contents/includes/modifiers as show.
     */
    protected function reload(Request $request, string $id): Entity
    {
        return $this->queries->single($request)->findOrFail($id);
    }
}
