<?php

namespace KusikusiCMS\Acl\Api\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user for role assignment: only `{id, name, email, roles[]}`, never secrets.
 *
 * @property Model $resource
 */
class AclUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'roles' => $this->when($user->relationLoaded('roles'), fn () => $user->getRelation('roles')->pluck('name')->values()->all()),
        ];
    }
}
