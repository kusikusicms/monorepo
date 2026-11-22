<?php

namespace KusikusiCMS\Acl;

/**
 * Gate abilities for managing roles, role permissions, user roles and the entity access view.
 * Each one resolves to Acl::canManage(), so apps can override a single ability with Gate::define or Gate::before.
 */
final class Abilities
{
    public const VIEW_ROLES = 'kusikusicms.acl.roles.view';

    public const MANAGE_ROLES = 'kusikusicms.acl.roles.manage';

    public const ASSIGN_ROLES = 'kusikusicms.acl.users.assign-roles';

    public const SEARCH_USERS = 'kusikusicms.acl.users.search';

    public const VIEW_ENTITY_ACCESS = 'kusikusicms.acl.entities.access';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_ROLES,
            self::MANAGE_ROLES,
            self::ASSIGN_ROLES,
            self::SEARCH_USERS,
            self::VIEW_ENTITY_ACCESS,
        ];
    }
}
