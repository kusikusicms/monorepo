<?php

namespace KusikusiCMS\Routes\Support;

use Illuminate\Support\Str;
use KusikusiCMS\Routes\EntityRoute;

/**
 * A request matched to a live entity through a primary or alias route.
 * Passed to the host controller's handle() method and to its model methods.
 */
final readonly class ResolvedRoute
{
    public function __construct(
        public string $entityId,
        public string $entityModel,
        public ?string $entityView,
        public string $format,
        public string $lang,
        public string $path,
        public EntityRoute $route,
    ) {}

    /**
     * The conventional view name: "{format}.{view}", e.g. "html.home" or "rss.news".
     * Without a stored view, the snake-cased model is used.
     */
    public function viewName(): string
    {
        return $this->format.'.'.($this->entityView ?: Str::snake($this->entityModel));
    }
}
