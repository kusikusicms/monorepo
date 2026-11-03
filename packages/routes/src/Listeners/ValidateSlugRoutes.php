<?php

namespace KusikusiCMS\Routes\Listeners;

use KusikusiCMS\Models\Events\EntityContentSaving;
use KusikusiCMS\Routes\Exceptions\RoutePathConflictException;
use KusikusiCMS\Routes\Services\RouteManager;

/**
 * Before a slug content is written, plan its routes (read-only) so a path conflict aborts the save.
 */
class ValidateSlugRoutes
{
    public function __construct(protected RouteManager $routes) {}

    /**
     * @throws RoutePathConflictException
     */
    public function handle(EntityContentSaving $event): void
    {
        $content = $event->content;
        if (! $this->routes->isSlugChange($content)) {
            return;
        }

        $this->routes->plan($content->entity_id, $content->lang, (string) $content->text);
    }
}
