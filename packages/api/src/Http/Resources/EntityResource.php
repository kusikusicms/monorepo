<?php

namespace KusikusiCMS\Api\Http\Resources;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityRelation;
use WeakMap;

/**
 * JSON shape of an entity. `contents` appears when the single-language contents were loaded (withContents),
 * `contents_by_lang` and `relations` with `?include=contents,relations`, `children` on tree responses.
 *
 * Other packages add fields with extend(): `$fields` returns extra keys per entity and the optional `$prepare`
 * receives every entity of the response once (a page, a tree, or one entity), so data can be loaded in batch.
 *
 * @mixin Entity
 */
class EntityResource extends JsonResource
{
    /** @var array<int, array{fields: Closure(Entity, Request): array<string, mixed>, prepare: (Closure(Collection<int, Entity>, Request): void)|null}> */
    private static array $extensions = [];

    /** @var WeakMap<Entity, true>|null Entities already prepared for the current response. */
    private static ?WeakMap $prepared = null;

    /**
     * Register extra fields: fn (Entity $entity, Request $request): array, and an optional batch
     * fn (Collection $entities, Request $request): void that runs before the fields of those entities.
     */
    public static function extend(Closure $fields, ?Closure $prepare = null): void
    {
        self::$extensions[] = ['fields' => $fields, 'prepare' => $prepare];
    }

    /**
     * Remove every extension (tests).
     */
    public static function flushExtensions(): void
    {
        self::$extensions = [];
        self::$prepared = null;
    }

    /**
     * Run the prepare callbacks once for entities not prepared yet (nested tree children included).
     *
     * @param  iterable<int, Entity>  $entities
     */
    public static function prepareAll(iterable $entities, Request $request): void
    {
        self::$prepared ??= new WeakMap;

        $pending = [];

        foreach (self::flatten($entities) as $entity) {
            if (! isset(self::$prepared[$entity])) {
                self::$prepared[$entity] = true;
                $pending[] = $entity;
            }
        }

        if ($pending === []) {
            return;
        }

        $pending = new Collection($pending);

        foreach (self::$extensions as $extension) {
            if ($extension['prepare'] !== null) {
                ($extension['prepare'])($pending, $request);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Entity $entity */
        $entity = $this->resource;

        self::prepareAll([$entity], $request);

        $data = [
            'id' => $entity->id,
            'model' => $entity->model,
            'view' => $entity->getAttribute('view'),
            'parent_entity_id' => $entity->parent_entity_id,
            'props' => (object) ($entity->props ?? []),
            'langs' => $entity->langs ?? [],
            'published' => (bool) $entity->published,
            'publish_at' => $entity->publish_at?->toJSON(),
            'unpublish_at' => $entity->unpublish_at?->toJSON(),
            'status' => $entity->status,
            'created_at' => $entity->getAttribute('created_at')?->toJSON(),
            'updated_at' => $entity->getAttribute('updated_at')?->toJSON(),
            'deleted_at' => $entity->getAttribute('deleted_at')?->toJSON(),
        ];

        if ($entity->relationLoaded('contentsSupport')) {
            $data['contents'] = (object) $entity->contentsSupport->pluck('text', 'field')->all();
        }

        if ($entity->relationLoaded('rawContents')) {
            $data['contents_by_lang'] = (object) $entity->getAttribute('contents_by_lang');
        }

        if ($entity->relationLoaded('relations')) {
            $data['relations'] = RelationResource::collection(
                $entity->relations->where('kind', '!=', EntityRelation::RELATION_ANCESTOR)->values()
            )->resolve($request);
        }

        foreach (self::$extensions as $extension) {
            $data = array_merge($data, ($extension['fields'])($entity, $request));
        }

        if ($entity->getAttribute('children') !== null) {
            $data['children'] = static::collection($entity->getAttribute('children'))->resolve($request);
        }

        return $data;
    }

    /**
     * Collections prepare every entity of the page at once.
     *
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): EntityResourceCollection
    {
        return new EntityResourceCollection($resource, static::class);
    }

    /**
     * The entities and, recursively, their tree children.
     *
     * @param  iterable<int, Entity>  $entities
     * @return \Generator<int, Entity>
     */
    private static function flatten(iterable $entities): \Generator
    {
        foreach ($entities as $entity) {
            yield $entity;

            $children = $entity->getAttribute('children');

            if ($children instanceof Collection) {
                yield from self::flatten($children);
            }
        }
    }
}
