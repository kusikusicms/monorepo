<?php

namespace KusikusiCMS\Acl\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KusikusiCMS\Acl\Support\RolePermissions;
use Spatie\Permission\Models\Role;

/**
 * A role: `{id, name, permissions_count?, users_count?, permissions?}`.
 *
 * @property Role $resource
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->resource;

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'permissions_count' => $this->when($role->getAttribute('permissions_count') !== null, fn () => (int) $role->getAttribute('permissions_count')),
            'users_count' => $this->when($role->getAttribute('users_count') !== null, fn () => (int) $role->getAttribute('users_count')),
            'permissions' => $this->when($role->relationLoaded('permissions'), fn () => self::permissions($role)),
        ];
    }

    /**
     * The permissions of a role grouped as `{entity: [...], manage: bool, other: [names]}`.
     *
     * @return array{entity: list<array<string, mixed>>, manage: bool, other: list<string>}
     */
    public static function permissions(Role $role): array
    {
        $grouped = RolePermissions::group($role->permissions->pluck('name')->map(fn ($name) => (string) $name)->all());

        return [
            'entity' => array_map(EntityPermissionResource::shape(...), $grouped['entity']),
            'manage' => $grouped['manage'],
            'other' => $grouped['other'],
        ];
    }
}
