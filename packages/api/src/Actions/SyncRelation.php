<?php

namespace KusikusiCMS\Api\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use KusikusiCMS\Api\Support\ApiException;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityRelation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates, updates and deletes non-ancestor relations of an entity. Ancestor relations are managed by the
 * models package from parent_entity_id and are never written here.
 */
final class SyncRelation
{
    /**
     * @param  array{called_entity_id: string, kind: string, position?: int|null, tags?: array<int, string>|null}  $data
     *
     * @throws ApiException 409 conflict when the relation already exists, 422 for the ancestor kind.
     */
    public function create(Entity $caller, array $data): EntityRelation
    {
        $this->guardKind($data['kind']);

        if ($this->query($caller, $data['called_entity_id'], $data['kind'])->exists()) {
            throw new ApiException('The relation already exists.', 'conflict', 409);
        }

        EntityRelation::query()->create([
            'caller_entity_id' => $caller->id,
            'called_entity_id' => $data['called_entity_id'],
            'kind' => $data['kind'],
            'position' => (int) ($data['position'] ?? 0),
            'depth' => 0,
            'tags' => $data['tags'] ?? [],
        ]);

        return $this->find($caller, $data['called_entity_id'], $data['kind']);
    }

    /**
     * @param  array{position?: int|null, tags?: array<int, string>|null}  $data
     */
    public function update(Entity $caller, string $calledId, string $kind, array $data): EntityRelation
    {
        $this->guardKind($kind);
        $relation = $this->find($caller, $calledId, $kind);

        $relation->fill(Arr::only($data, ['position', 'tags']));

        if (array_key_exists('tags', $data) && $data['tags'] === null) {
            $relation->setAttribute('tags', []);
        }

        $relation->save();

        return $relation;
    }

    public function delete(Entity $caller, string $calledId, string $kind): void
    {
        $this->guardKind($kind);
        $this->find($caller, $calledId, $kind)->delete();
    }

    /**
     * @throws NotFoundHttpException
     */
    public function find(Entity $caller, string $calledId, string $kind): EntityRelation
    {
        /** @var EntityRelation|null $relation */
        $relation = $this->query($caller, $calledId, $kind)->first();

        if ($relation === null) {
            throw new NotFoundHttpException('Relation not found.');
        }

        return $relation;
    }

    /**
     * @return Builder<EntityRelation>
     */
    protected function query(Entity $caller, string $calledId, string $kind)
    {
        return EntityRelation::query()
            ->where('caller_entity_id', $caller->id)
            ->where('called_entity_id', $calledId)
            ->where('kind', $kind);
    }

    /**
     * @throws ApiException
     */
    protected function guardKind(string $kind): void
    {
        if ($kind === EntityRelation::RELATION_ANCESTOR) {
            throw new ApiException('Ancestor relations are managed through parent_entity_id.', 'validation_failed', 422, [
                'kind' => ['Ancestor relations are managed through parent_entity_id.'],
            ]);
        }
    }
}
