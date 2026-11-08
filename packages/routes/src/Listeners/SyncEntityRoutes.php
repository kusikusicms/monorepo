<?php

namespace KusikusiCMS\Routes\Listeners;

use KusikusiCMS\Models\Events\EntitySaved;
use KusikusiCMS\Models\Events\EntitySaving;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;
use KusikusiCMS\Routes\Services\PathBuilder;
use KusikusiCMS\Routes\Services\RouteManager;

/**
 * Keeps routes in sync with entity changes: re-paths an entity and its descendants when it moves
 * to another parent, and refreshes the denormalised model and view columns.
 */
class SyncEntityRoutes
{
    public function __construct(protected RouteManager $routes, protected PathBuilder $paths) {}

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
        $domainLang = $this->paths->domainLang();
        if ($entity->wasChanged('parent_entity_id')) {
            $this->routes->syncEntity($entity);
        } elseif ($domainLang !== null && $this->paths->isDefaultRoot($entity, $domainLang) && ($entity->wasRecentlyCreated || $entity->wasChanged(['model', 'view']))) {
            // The root needs no slug content: it always gets "/" in the domain language
            $this->routes->syncEntity($entity, $domainLang);
        }
        if ($entity->wasChanged(['model', 'view'])) {
            $this->routes->refreshDenormalized($entity);
        }
    }
}
