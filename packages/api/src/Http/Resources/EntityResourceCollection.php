<?php

namespace KusikusiCMS\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A collection of EntityResource that runs the extension `prepare` callbacks once for the whole page.
 */
class EntityResourceCollection extends AnonymousResourceCollection
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        EntityResource::prepareAll($this->collection->map(fn (EntityResource $item) => $item->resource), $request);

        return parent::toArray($request);
    }
}
