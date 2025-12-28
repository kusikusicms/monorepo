<?php

namespace KusikusiCMS\Models\Listeners;

use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Events\EntityCreating;
use KusikusiCMS\Models\Events\EntitySaved;

class EntityEventSubscriber
{
    /**
     * Handle EntityCreating event.
     */
    public function entityCreating(EntityCreating $event): void
    {
        // Check if the id is already in use
        if (Entity::find($event->entity[$event->entity->getKeyName()])) {
            abort(403, 'Duplicated Entity ID "'.$event->entity[$event->entity->getKeyName()]).'"';
        }
        // Setting default values, some of them can not be set as default attributes.
        if (! isset($event->entity->model)) {
            $event->entity->model = 'Entity';
        }
        if (! isset($event->entity->publish_at)) {
            $event->entity->publish_at = Carbon::now();
        }
        if (! isset($event->entity->view)) {
            $event->entity->view = Str::snake($event->entity['model']);
        }
        if (! isset($event->entity->props)) {
            $event->entity->props = new \ArrayObject;
        }
        if (! isset($event->entity->published)) {
            $event->entity->published = true;
        }
    }

    /**
     * Handle EntitySaved event
     */
    public function entitySaved(EntitySaved $event): void
    {
        if ($event->entity->isDirty('parent_entity_id')) {
            $event->entity->refreshAncestorsRelations();
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            EntityCreating::class => 'entityCreating',
            EntitySaved::class => 'entitySaved',
        ];
    }
}
