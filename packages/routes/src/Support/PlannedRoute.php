<?php

namespace KusikusiCMS\Routes\Support;

/**
 * The primary path an entity should have in a language once a RoutePlan is applied.
 * A null path means the entity's primary route in that language is removed.
 */
final readonly class PlannedRoute
{
    public function __construct(
        public string $entityId,
        public string $lang,
        public ?string $path,
        public string $entityModel,
        public ?string $entityView,
    ) {}
}
