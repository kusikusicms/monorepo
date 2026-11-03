<?php

namespace KusikusiCMS\Routes\Listeners;

use KusikusiCMS\Models\Events\EntitySaved;
use KusikusiCMS\Models\Events\EntitySaving;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;
use KusikusiCMS\Routes\Services\RouteManager;

/**
 * Keeps routes in sync with entity changes: re-paths an entity and its descendants when it moves
 * to another parent, and refreshes the denormalised model and view columns.
 */
class SyncEntityRoutes
{
    public function __construct(protected RouteManager $routes) {}

    /**
     * Before a parent change is saved, validate the new paths so a conflict aborts the save.
     *
     * @throws RoutePathConflictException
     */
    public function validate(EntitySaving $event): void
    {
        $entity = $event->entity;
        if ($entity->exists && $entity->isDirty('parent_entity_id')) {
            $this->routes->validateEntity($entity);
        }
    }

    /**
     * After the entity is saved, write the new paths and denormalised columns.
     *
     * @throws RoutePathConflictException
     */
    public function handle(EntitySaved $event): void
    {
        $entity = $event->entity;
        if ($entity->wasChanged('parent_entity_id')) {
            $this->routes->syncEntity($entity);
        }
        if ($entity->wasChanged(['model', 'view'])) {
            $this->routes->refreshDenormalized($entity);
        }
    }
}
