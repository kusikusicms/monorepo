<?php

namespace KusikusiCMS\Models\Observers;

use KusikusiCMS\Models\Entity;

/**
 * Observer for Entity lifecycle events.
 *
 * Keeps internal tree relations consistent and leaves domain event dispatching
 * to the model's $dispatchesEvents so host apps can listen to them.
 */
class EntityObserver
{
    /**
     * Handle the Entity "saved" event.
     *
     * If the parent_entity_id changed, refresh ancestor relations for the entity
     * and its descendants.
     */
    public function saved(Entity $entity): void
    {
        if ($entity->wasChanged('parent_entity_id')) {
            Entity::refreshAncestorRelationsOfEntity($entity);
        }
    }

    // The following methods are defined for future extension and clarity.
    public function creating(Entity $entity): void {}
    public function created(Entity $entity): void {}
    public function updating(Entity $entity): void {}
    public function updated(Entity $entity): void {}
    public function saving(Entity $entity): void {}
    public function deleting(Entity $entity): void {}
    public function deleted(Entity $entity): void {}
    public function restoring(Entity $entity): void {}
    public function restored(Entity $entity): void {}
    public function forceDeleted(Entity $entity): void {}
    public function replicating(Entity $entity): void {}
}
