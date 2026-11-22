<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Api\Actions\SyncRelation;
use KusikusiCMS\Api\Http\Requests\StoreRelationRequest;
use KusikusiCMS\Api\Http\Requests\UpdateRelationRequest;
use KusikusiCMS\Api\Http\Resources\RelationResource;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityRelation;

/**
 * Non-ancestor relations from {entity} to other entities.
 */
class EntityRelationsController extends Controller
{
    public function index(Request $request, string $entity): AnonymousResourceCollection
    {
        $request->validate(['kind' => ['sometimes', 'string', 'max:24']]);

        $model = Entity::query()->findOrFail($entity);
        Gate::authorize('view', $model);

        $relations = $model->relations()
            ->where('kind', '!=', EntityRelation::RELATION_ANCESTOR)
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->query('kind')))
            ->orderBy('kind')
            ->orderBy('position')
            ->get();

        return RelationResource::collection($relations);
    }

    public function store(StoreRelationRequest $request, SyncRelation $sync): JsonResponse
    {
        /** @var array{called_entity_id: string, kind: string, position?: int|null, tags?: array<int, string>|null} $data */
        $data = $request->validated();
        $relation = $sync->create($request->entity(), $data);

        return RelationResource::make($relation)->response()->setStatusCode(201);
    }

    public function update(UpdateRelationRequest $request, SyncRelation $sync, string $entity, string $called, string $kind): RelationResource
    {
        /** @var array{position?: int|null, tags?: array<int, string>|null} $data */
        $data = $request->validated();

        return RelationResource::make($sync->update($request->entity(), $called, $kind, $data));
    }

    public function destroy(UpdateRelationRequest $request, SyncRelation $sync, string $entity, string $called, string $kind): JsonResponse
    {
        $sync->delete($request->entity(), $called, $kind);

        return response()->json(null, 204);
    }
}
