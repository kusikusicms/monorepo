<?php

namespace KusikusiCMS\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KusikusiCMS\Models\EntityRelation;

/**
 * @mixin EntityRelation
 */
class RelationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'caller_entity_id' => $this->resource->getAttribute('caller_entity_id'),
            'called_entity_id' => $this->resource->getAttribute('called_entity_id'),
            'kind' => $this->resource->getAttribute('kind'),
            'position' => (int) $this->resource->getAttribute('position'),
            'depth' => (int) $this->resource->getAttribute('depth'),
            'tags' => $this->resource->getAttribute('tags') ?? [],
        ];
    }
}
