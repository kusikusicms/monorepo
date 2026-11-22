<?php

namespace KusikusiCMS\Api\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use KusikusiCMS\Models\Entity;

/**
 * Partially updates an entity (only the given attributes) and optionally its contents, in one transaction,
 * through save() and createContents() so every model event fires.
 */
final class UpdateEntity
{
    /**
     * @param  array<string, mixed>  $data  Validated input; `contents` is {lang: {field: text|null}}.
     */
    public function __invoke(Entity $entity, array $data): Entity
    {
        return DB::transaction(function () use ($entity, $data) {
            $entity->fill(Arr::only($data, array_diff(CreateEntity::ATTRIBUTES, ['id'])));

            if ($entity->isDirty()) {
                $entity->save();
            }

            foreach ((array) ($data['contents'] ?? []) as $lang => $fields) {
                $entity->createContents((array) $fields, (string) $lang);
            }

            return $entity;
        });
    }
}
