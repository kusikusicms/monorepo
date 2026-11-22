<?php

namespace KusikusiCMS\Acl\Support;

use KusikusiCMS\Acl\Acl;
use KusikusiCMS\Acl\Exceptions\InvalidEntityPermissionException;

/**
 * Splits permission names into entity permissions (parsed), the manage permission and any other name.
 */
final class RolePermissions
{
    /**
     * @param  iterable<string>  $names
     * @return array{entity: list<EntityPermission>, manage: bool, other: list<string>}
     */
    public static function group(iterable $names): array
    {
        $entity = [];
        $manage = false;
        $other = [];

        foreach ($names as $name) {
            if ($name === Acl::managePermission()) {
                $manage = true;

                continue;
            }

            try {
                $parsed = EntityPermission::parse($name);
            } catch (InvalidEntityPermissionException) {
                $parsed = null;
            }

            if ($parsed === null) {
                $other[] = $name;
            } else {
                $entity[] = $parsed;
            }
        }

        return ['entity' => $entity, 'manage' => $manage, 'other' => $other];
    }

    /**
     * Whether a permission name is an entity permission (it starts with the configured prefix).
     */
    public static function isEntityPermission(string $name): bool
    {
        return explode('.', $name)[0] === config('kusikusicms.acl.prefix', 'entities');
    }

    /**
     * Whether two entity permissions grant the same actions, ids and scopes, in any order.
     */
    public static function same(EntityPermission $a, EntityPermission $b): bool
    {
        $sorted = static function (array $parts): array {
            sort($parts);

            return $parts;
        };

        return $sorted($a->actions) === $sorted($b->actions)
            && $sorted($a->entityIds) === $sorted($b->entityIds)
            && $sorted($a->scopes) === $sorted($b->scopes);
    }
}
