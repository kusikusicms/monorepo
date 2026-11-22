<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Acl\Abilities;
use KusikusiCMS\Acl\Api\Http\Resources\EntityAccessResource;
use KusikusiCMS\Acl\Services\EntityAccessInspector;
use KusikusiCMS\Models\Entity;

/**
 * GET /entities/{id}/permissions: which roles can do what on one entity, and through which permissions.
 */
class EntityAccessController extends Controller
{
    public function __invoke(string $entity, EntityAccessInspector $inspector): AnonymousResourceCollection
    {
        Gate::authorize(Abilities::VIEW_ENTITY_ACCESS);

        /** @var Entity $model */
        $model = Entity::query()->select(['id', 'parent_entity_id'])->findOrFail($entity);

        return EntityAccessResource::collection($inspector->forEntity($model));
    }
}
