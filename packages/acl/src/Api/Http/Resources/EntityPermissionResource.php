<?php

namespace KusikusiCMS\Acl\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KusikusiCMS\Acl\Support\EntityPermission;

/**
 * A parsed entity permission: `{name, action[], entity_id[], scope[]}`.
 *
 * @property EntityPermission $resource
 */
class EntityPermissionResource extends JsonResource
{
    /**
     * @return array{name: string, action: list<string>, entity_id: list<string>, scope: list<string>}
     */
    public function toArray(Request $request): array
    {
        return self::shape($this->resource);
    }

    /**
     * @return array{name: string, action: list<string>, entity_id: list<string>, scope: list<string>}
     */
    public static function shape(EntityPermission $permission): array
    {
        return [
            'name' => $permission->name(),
            'action' => $permission->actions,
            'entity_id' => $permission->entityIds,
            'scope' => $permission->scopes,
        ];
    }
}
