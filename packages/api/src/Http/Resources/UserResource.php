<?php

namespace KusikusiCMS\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user as returned by login and /me. Only non-secret fields are exposed.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name ?? null,
            'email' => $this->resource->email ?? null,
        ];
    }
}
