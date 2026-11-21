<?php

namespace KusikusiCMS\Api\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use KusikusiCMS\Models\Entity;

/**
 * Creates an entity and its contents in one transaction. The entity is saved first (so EntitySaved listeners,
 * such as route syncing, run with the entity in place), then contents are written per language with
 * createContents(), which fires the EntityContent events. Any exception rolls everything back.
 */
final class CreateEntity
{
    /**
     * Writable entity attributes.
     */
    public const ATTRIBUTES = ['id', 'model', 'view', 'parent_entity_id', 'props', 'published', 'publish_at', 'unpublish_at', 'langs'];

    /**
     * @param  array<string, mixed>  $data  Validated input; `contents` is {lang: {field: text|null}}.
     */
    public function __invoke(array $data): Entity
    {
        return DB::transaction(function () use ($data) {
            $entity = new Entity;
            $entity->fill(Arr::only($data, self::ATTRIBUTES));
            $entity->save();

            foreach ((array) ($data['contents'] ?? []) as $lang => $fields) {
                $entity->createContents((array) $fields, (string) $lang);
            }

            return $entity;
        });
    }
}
