<?php

namespace KusikusiCMS\Routes\Support;

/**
 * The validated primary routes of an entity and its descendants in one language.
 * Built (read-only) by RouteManager::plan() and written by RouteManager::apply().
 */
final readonly class RoutePlan
{
    /**
     * @param  list<PlannedRoute>  $routes
     */
    public function __construct(
        public string $lang,
        public array $routes = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->routes === [];
    }

    /**
     * The planned path of an entity, or null when it is not part of the plan or gets no path.
     */
    public function pathOf(string $entityId): ?string
    {
        foreach ($this->routes as $route) {
            if ($route->entityId === $entityId) {
                return $route->path;
            }
        }

        return null;
    }
}
