<?php

namespace KusikusiCMS\Acl\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One role's access to an entity: `{role, actions: {view, create, update, delete}, via: [...]}`.
 *
 * @property array{role: string, actions: array<string, bool>, via: list<array<string, mixed>>} $resource
 */
class EntityAccessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->resource['role'],
            'actions' => $this->resource['actions'],
            'via' => $this->resource['via'],
        ];
    }
}
