<?php

namespace KusikusiCMS\Routes\Listeners;

use KusikusiCMS\Models\Events\EntityContentDeleted;
use KusikusiCMS\Models\Events\EntityContentSaved;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;
use KusikusiCMS\Routes\Services\RouteManager;

/**
 * After a slug content is saved or deleted, write the routes of its entity and descendants.
 */
class SyncSlugRoutes
{
    public function __construct(protected RouteManager $routes) {}

    /**
     * @throws RoutePathConflictException
     */
    public function handle(EntityContentSaved|EntityContentDeleted $event): void
    {
        $content = $event->content;

        if ($event instanceof EntityContentDeleted) {
            if ($content->field !== config('kusikusicms.routes.slug_field', 'slug')) {
                return;
            }
            $plan = $this->routes->plan($content->entity_id, $content->lang, deleted: true);
        } else {
            if (! $this->routes->isSlugChange($content)) {
                return;
            }
            $plan = $this->routes->plan($content->entity_id, $content->lang, (string) $content->text);
        }

        $this->routes->apply($plan);
    }
}
