<?php

namespace KusikusiCMS\Routes\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KusikusiCMS\Routes\EntityRoute;

/**
 * @mixin EntityRoute
 */
class EntityRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EntityRoute $route */
        $route = $this->resource;

        return [
            'route_id' => $route->route_id,
            'entity_id' => $route->entity_id,
            'path' => $route->path,
            'lang' => $route->lang,
            'kind' => $route->kind->value,
            'entity_model' => $route->entity_model,
            'entity_view' => $route->entity_view,
        ];
    }
}
